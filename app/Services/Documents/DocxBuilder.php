<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\EducationLevel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use RuntimeException;
use ZipArchive;

/**
 * Turns a validated document array into a campus-format DOCX.
 *
 * Chapter and section numbers are computed here, never read from the data.
 * The PHPWord workarounds (integer twips, A4 size, XML escaping, roman page numbers,
 * a real TOC field, outline levels, hanging indent) come from the PRD spike findings.
 */
class DocxBuilder
{
    private const A4_WIDTH_TWIPS = 11906;

    private const A4_HEIGHT_TWIPS = 16838;

    private const TOC_PLACEHOLDER = '[[TOC]]';

    public function __construct(private readonly SchemaValidator $validator) {}

    /**
     * @param  array<string, mixed>  $document
     * @return string absolute path of the generated .docx
     */
    public function build(array $document, Template $template, EducationLevel $level = EducationLevel::Mahasiswa): string
    {
        $errors = $this->validator->validate($document);

        if ($errors !== []) {
            throw new InvalidArgumentException("Invalid document:\n".implode("\n", $errors));
        }

        Settings::setOutputEscapingEnabled(true);

        $word = new PhpWord;
        $word->getSettings()->setUpdateFields(true);
        $word->setDefaultFontName($template->fontName);
        $word->setDefaultFontSize($template->fontSize);
        $word->setDefaultParagraphStyle([
            'spacing' => 0,
            'spaceAfter' => 0,
            'lineHeight' => $template->lineSpacing,
            'alignment' => Jc::BOTH,
        ]);

        $this->addStyles($word, $template);
        $page = $this->pageStyle($template);

        $this->addCover($word, $document, $page, $level);
        $this->addFrontMatter($word, $document, $page);

        foreach ($document['chapters'] as $index => $chapter) {
            $this->addChapter($word, $chapter, $index, $page, $template);
        }

        $this->addReferences($word, $document['references'] ?? [], $page, $template);

        File::ensureDirectoryExists(storage_path('app/documents'));
        $path = storage_path('app/documents/'.Str::uuid().'.docx');
        IOFactory::createWriter($word, 'Word2007')->save($path);

        $this->patchPackage($path);

        return $path;
    }

    private function addStyles(PhpWord $word, Template $template): void
    {
        $heading = ['bold' => true, 'size' => $template->fontSize];

        $word->addTitleStyle(1, $heading, ['alignment' => Jc::CENTER, 'spaceAfter' => $this->pt(12), 'keepNext' => true]);
        $word->addTitleStyle(2, $heading, ['alignment' => Jc::START, 'spaceBefore' => $this->pt(12), 'keepNext' => true]);

        $level = ['left' => 720, 'hanging' => 360, 'tabPos' => 720];
        $word->addNumberingStyle('ordered', ['type' => 'multilevel', 'levels' => [$level + ['format' => 'decimal', 'text' => '%1.']]]);
        $word->addNumberingStyle('unordered', ['type' => 'multilevel', 'levels' => [$level + ['format' => 'bullet', 'text' => '•']]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageStyle(Template $template): array
    {
        return [
            'marginLeft' => $this->cm($template->marginLeftCm),
            'marginTop' => $this->cm($template->marginTopCm),
            'marginRight' => $this->cm($template->marginRightCm),
            'marginBottom' => $this->cm($template->marginBottomCm),
            'pageSizeW' => self::A4_WIDTH_TWIPS,
            'pageSizeH' => self::A4_HEIGHT_TWIPS,
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $page
     */
    private function addCover(PhpWord $word, array $document, array $page, EducationLevel $level): void
    {
        $cover = $document['cover'];
        $center = ['alignment' => Jc::CENTER];
        $bold = ['bold' => true];

        $section = $word->addSection($page);
        $section->addTextBreak(2);
        $section->addText(DocumentType::from($document['type'])->coverLabel(), ['bold' => true, 'size' => 14], $center);
        $section->addTextBreak();
        $section->addText(mb_strtoupper($cover['title']), ['bold' => true, 'size' => 14], $center);
        $section->addTextBreak(6);
        $section->addText('Disusun oleh:', null, $center);
        $text = $level->cover($cover);
        $section->addText($text['person'], $bold, $center);
        $section->addTextBreak(6);

        foreach ($text['foot'] as $line) {
            $section->addText(mb_strtoupper($line), $bold, $center);
        }
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>  $page
     */
    private function addFrontMatter(PhpWord $word, array $document, array $page): void
    {
        $section = $word->addSection($page + ['pageNumberingStart' => 1, 'breakType' => 'nextPage']);
        $section->addFooter()->addPreserveText('{PAGE}', null, ['alignment' => Jc::CENTER]);

        $section->addTitle('KATA PENGANTAR', 1);
        $section->addText('Puji syukur kami panjatkan ke hadirat Tuhan Yang Maha Esa atas terselesaikannya '.mb_strtolower(DocumentType::from($document['type'])->coverLabel()).' berjudul "'.$document['cover']['title'].'" ini.');
        $section->addPageBreak();
        $section->addText('DAFTAR ISI', ['bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => $this->pt(12)]);
        $section->addText(self::TOC_PLACEHOLDER);
    }

    /**
     * @param  array<string, mixed>  $chapter
     * @param  array<string, mixed>  $page
     */
    private function addChapter(PhpWord $word, array $chapter, int $index, array $page, Template $template): void
    {
        $number = $index + 1;
        $style = $page + ['breakType' => 'nextPage'];

        if ($index === 0) {
            $style['pageNumberingStart'] = 1;
        }

        $section = $word->addSection($style);
        $section->addFooter()->addPreserveText('{PAGE}', null, ['alignment' => Jc::CENTER]);
        $section->addTitle('BAB '.$this->roman($number).' '.mb_strtoupper($chapter['title']), 1);

        foreach ($chapter['sections'] as $sectionIndex => $subsection) {
            $section->addTitle($number.'.'.($sectionIndex + 1).' '.$subsection['title'], 2);

            foreach ($subsection['blocks'] as $block) {
                if ($block['type'] === 'paragraph') {
                    $section->addText($block['text'], null, ['indentation' => ['firstLine' => $this->cm($template->firstLineIndentCm)]]);

                    continue;
                }

                foreach ($block['items'] as $item) {
                    $section->addListItem($item, 0, null, $block['ordered'] ? 'ordered' : 'unordered');
                }
            }
        }
    }

    /**
     * @param  list<array{id: string, text: string}>  $references
     * @param  array<string, mixed>  $page
     */
    private function addReferences(PhpWord $word, array $references, array $page, Template $template): void
    {
        $section = $word->addSection($page + ['breakType' => 'nextPage']);
        $section->addFooter()->addPreserveText('{PAGE}', null, ['alignment' => Jc::CENTER]);
        $section->addTitle('DAFTAR PUSTAKA', 1);

        if ($references === []) {
            $section->addText('[Tambahkan referensi]');

            return;
        }

        $texts = array_column($references, 'text');
        usort($texts, fn (string $a, string $b): int => strcmp(mb_strtolower($a), mb_strtolower($b)));

        $hanging = $this->cm($template->hangingIndentCm);

        foreach ($texts as $text) {
            $section->addText($text, null, ['indentation' => ['left' => $hanging, 'hanging' => $hanging], 'spaceAfter' => $this->pt(6)]);
        }
    }

    /**
     * XML patches PHPWord cannot express: roman front-matter numbering, a real TOC field,
     * hanging indent without firstLine, and outline levels on the heading styles.
     */
    private function patchPackage(string $path): void
    {
        $zip = new ZipArchive;
        $zip->open($path);

        $xml = (string) $zip->getFromName('word/document.xml');

        $xml = preg_replace('/<w:pgNumType w:start="1"\/>/', '<w:pgNumType w:fmt="lowerRoman" w:start="1"/>', $xml, 1, $romanCount);

        $toc = '<w:p><w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r>'
            .'<w:r><w:instrText xml:space="preserve"> TOC \o "1-2" \h \z \u </w:instrText></w:r>'
            .'<w:r><w:fldChar w:fldCharType="separate"/></w:r>'
            .'<w:r><w:t>Klik kanan lalu Update Field untuk menampilkan daftar isi.</w:t></w:r>'
            .'<w:r><w:fldChar w:fldCharType="end"/></w:r></w:p>';
        $xml = preg_replace('/<w:p>(?:(?!<w:p>).)*?'.preg_quote(self::TOC_PLACEHOLDER, '/').'.*?<\/w:p>/s', $toc, $xml, 1, $tocCount);

        // ponytail: firstLine="0" next to hanging makes LibreOffice ignore the hanging indent.
        $xml = preg_replace('/ w:firstLine="0"( w:hanging="[1-9])/', '$1', $xml);

        $styles = (string) $zip->getFromName('word/styles.xml');
        $outlineCount = 0;

        foreach ([1 => 0, 2 => 1] as $heading => $level) {
            // outlineLvl belongs at the end of pPr in the OOXML sequence, so insert before its closing tag.
            $styles = preg_replace('/(w:styleId="Heading'.$heading.'".*?)(<\/w:pPr>)/s', '$1<w:outlineLvl w:val="'.$level.'"/>$2', $styles, 1, $count);
            $outlineCount += $count;
        }

        if ($romanCount !== 1 || $tocCount !== 1 || $outlineCount !== 2) {
            $zip->close();

            throw new RuntimeException('DOCX post-processing did not find its anchors; PHPWord output has changed.');
        }

        $zip->addFromString('word/document.xml', $xml);
        $zip->addFromString('word/styles.xml', $styles);
        $zip->close();
    }

    /** OOXML needs integer twips; the Converter returns floats that LibreOffice rejects. */
    private function cm(float $value): int
    {
        return (int) round(Converter::cmToTwip($value));
    }

    private function pt(float $value): int
    {
        return (int) round(Converter::pointToTwip($value));
    }

    private function roman(int $number): string
    {
        $map = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
        $out = '';

        foreach ($map as $symbol => $value) {
            while ($number >= $value) {
                $out .= $symbol;
                $number -= $value;
            }
        }

        return $out;
    }
}
