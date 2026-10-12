<?php

namespace App\Services\Documents;

use App\Enums\EducationLevel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use RuntimeException;
use ZipArchive;

/**
 * Renders the DocumentLayout plan as a campus-format DOCX. This class adds only Word-specific
 * formatting: A4 page size, styles, section breaks, and page numbers. What appears, and in what
 * order, comes from DocumentLayout, which the A4 preview also uses.
 *
 * The PHPWord workarounds (integer twips, A4 size, XML escaping, roman page numbers,
 * a real TOC field, outline levels, hanging indent) come from the PRD spike findings.
 */
class DocxBuilder
{
    private const A4_WIDTH_TWIPS = 11906;

    private const A4_HEIGHT_TWIPS = 16838;

    private const TOC_PLACEHOLDER = '[[TOC]]';

    public function __construct(
        private readonly SchemaValidator $validator,
        private readonly DocumentLayout $layout,
    ) {}

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
        $plan = $this->layout->plan($document, $level);

        $this->addCover($word, $plan['cover'], $page);
        $this->addFrontMatter($word, $plan, $page, $template);

        foreach ($plan['chapters'] as $chapter) {
            $this->addChapter($word, $chapter, $page, $template, $chapter['index'] === 0);
        }

        $this->addReferences($word, $plan['references'], $page, $template);
        $this->addAppendices($word, $plan['appendices'], $page, $template);

        File::ensureDirectoryExists(storage_path('app/documents'));
        $path = storage_path('app/documents/'.Str::uuid().'.docx');
        IOFactory::createWriter($word, 'Word2007')->save($path);

        $this->patchPackage($path, self::A4_WIDTH_TWIPS - $page['marginLeft'] - $page['marginRight'], count($plan['chapters']));

        return $path;
    }

    private function addStyles(PhpWord $word, Template $template): void
    {
        $heading = ['bold' => true, 'size' => $template->fontSize];

        $word->addTitleStyle(1, $heading, ['alignment' => Jc::CENTER, 'spaceAfter' => $this->pt(24), 'keepNext' => true]);
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
     * @param  array<string, mixed>  $cover  from DocumentLayout
     * @param  array<string, mixed>  $page
     */
    private function addCover(PhpWord $word, array $cover, array $page): void
    {
        $center = ['alignment' => Jc::CENTER];
        $bold = ['bold' => true];

        $section = $word->addSection($page);
        $section->addTextBreak(2);
        $section->addText($cover['label'], ['bold' => true, 'size' => 14], $center);
        $section->addTextBreak();
        $section->addText($cover['title'], ['bold' => true, 'size' => 14], $center);
        $section->addTextBreak(6);
        $section->addText('Disusun oleh:', $bold, $center);

        if ($cover['name'] !== null) {
            $section->addText($cover['name'], $bold + ['underline' => 'single'], $center);
        }

        if ($cover['nim'] !== null) {
            $section->addText($cover['nim'], $bold, $center);
        }

        $section->addTextBreak(9);

        foreach ($cover['foot'] as $line) {
            $section->addText($line, $bold, $center);
        }
    }

    /**
     * Front matter in the order the plan gives: approval, abstract, preface, table of contents.
     *
     * @param  array<string, mixed>  $plan
     * @param  array<string, mixed>  $page
     */
    private function addFrontMatter(PhpWord $word, array $plan, array $page, Template $template): void
    {
        $section = $word->addSection($page + ['pageNumberingStart' => 1, 'breakType' => 'nextPage']);
        $section->addFooter()->addPreserveText('{PAGE}', null, ['alignment' => Jc::CENTER]);
        $indent = ['indentation' => ['firstLine' => $this->cm($template->firstLineIndentCm)]];

        foreach ($plan['front'] as $item) {
            match ($item['key']) {
                'approval' => $this->addApproval($section, $plan['approval']),
                'abstract' => $this->addTitleAndText($section, $item['title'], $plan['abstract'], $indent),
                'preface' => $this->addPreface($section, $plan['preface'], $indent),
                'toc' => $this->addTocPlaceholder($section, $item['title']),
                default => null,
            };

            if ($item['key'] !== 'toc') {
                $section->addPageBreak();
            }
        }
    }

    /** @param  array<string, mixed>  $approval */
    private function addApproval(Section $section, array $approval): void
    {
        $section->addTitle('LEMBAR PENGESAHAN', 1);
        $section->addText($approval['sentence'], null, ['indentation' => ['firstLine' => $this->cm(1.25)]]);
        $section->addTextBreak();

        foreach ($approval['lines'] as $index => $line) {
            if ($index === 2) {
                $section->addTextBreak(2);
            }

            $section->addText($line['label'].': '.$line['value']);
        }
    }

    /** @param  array<string, mixed>  $indent */
    private function addTitleAndText(Section $section, string $title, string $text, array $indent): void
    {
        $section->addTitle($title, 1);
        $section->addText($text, null, $indent);
    }

    /**
     * @param  array<string, mixed>  $preface
     * @param  array<string, mixed>  $indent
     */
    private function addPreface(Section $section, array $preface, array $indent): void
    {
        $section->addTitle($preface['title'], 1);
        $section->addText($preface['text'], null, $indent);
        $section->addTextBreak();
        $section->addText($preface['signature'], null, ['alignment' => Jc::END]);
    }

    /** Word fills this field in when the user updates fields; the placeholder is swapped out in patchPackage(). */
    private function addTocPlaceholder(Section $section, string $title): void
    {
        $section->addTitle($title, 1);
        $section->addText(self::TOC_PLACEHOLDER);
    }

    /**
     * @param  array<string, mixed>  $chapter  one entry of DocumentLayout::plan()['chapters']
     * @param  array<string, mixed>  $page
     */
    private function addChapter(PhpWord $word, array $chapter, array $page, Template $template, bool $first): void
    {
        $style = $page + ['breakType' => 'nextPage'];

        if ($first) {
            $style['pageNumberingStart'] = 1;
        }

        $section = $word->addSection($style);
        $this->addBodyPageNumbers($section);

        // "BAB I" and the title on separate lines, still one heading so the TOC reads "BAB I PENDAHULUAN".
        $heading = new TextRun;
        $heading->addText($chapter['label']);
        $heading->addTextBreak();
        $heading->addText($chapter['title']);
        $section->addTitle($heading, 1);

        foreach ($chapter['sections'] as $subsection) {
            $section->addTitle($subsection['number'].' '.$subsection['title'], 2);

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
     * @param  array{title: string, entries: list<string>}  $references
     * @param  array<string, mixed>  $page
     */
    private function addReferences(PhpWord $word, array $references, array $page, Template $template): void
    {
        $section = $word->addSection($page + ['breakType' => 'nextPage']);
        $this->addBodyPageNumbers($section);
        $section->addTitle($references['title'], 1);

        if ($references['entries'] === []) {
            $section->addText('[Tambahkan referensi]');

            return;
        }

        $hanging = $this->cm($template->hangingIndentCm);

        foreach ($references['entries'] as $line) {
            $section->addText($line, null, ['indentation' => ['left' => $hanging, 'hanging' => $hanging], 'spaceAfter' => $this->pt(6)]);
        }
    }

    /**
     * Lampiran comes after the bibliography. Each appendix is a Heading 2 (TOC level 2); its text keeps one paragraph per line.
     *
     * @param  array{title: string, items: list<array{title: string, lines: list<string>}>}|null  $appendices
     * @param  array<string, mixed>  $page
     */
    private function addAppendices(PhpWord $word, ?array $appendices, array $page, Template $template): void
    {
        if ($appendices === null) {
            return;
        }

        $section = $word->addSection($page + ['breakType' => 'nextPage']);
        $this->addBodyPageNumbers($section);
        $section->addTitle($appendices['title'], 1);

        if ($appendices['items'] === []) {
            $section->addText('[Tambahkan lampiran]');

            return;
        }

        foreach ($appendices['items'] as $appendix) {
            $section->addTitle($appendix['title'], 2);

            foreach ($appendix['lines'] as $line) {
                $section->addText($line, null, ['indentation' => ['firstLine' => $this->cm($template->firstLineIndentCm)]]);
            }
        }
    }

    /**
     * Indonesian convention: a chapter's first page has its number bottom-centre, later pages top-right.
     * Every slot is set explicitly, otherwise Word inherits the roman footer from the front matter.
     */
    private function addBodyPageNumbers(Section $section): void
    {
        $section->addHeader(Header::FIRST);
        $section->addFooter(Header::FIRST)->addPreserveText('{PAGE}', null, ['alignment' => Jc::CENTER]);
        $section->addHeader()->addPreserveText('{PAGE}', null, ['alignment' => Jc::END]);
        $section->addFooter();
    }

    /**
     * XML patches PHPWord cannot express: roman front-matter numbering, a real TOC field,
     * hanging indent without firstLine, and outline levels on the heading styles.
     */
    private function patchPackage(string $path, int $textWidth, int $chapterCount): void
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

        // PHPWord writes a TextRun line break as a bare <w:br/> in the paragraph; OOXML needs it inside a run.
        $xml = preg_replace('/<\/w:r><w:br\/><w:r>/', '</w:r><w:r><w:br/></w:r><w:r>', $xml, -1, $breakCount);

        // ponytail: firstLine="0" next to hanging makes LibreOffice ignore the hanging indent.
        $xml = preg_replace('/ w:firstLine="0"( w:hanging="[1-9])/', '$1', $xml);

        $styles = (string) $zip->getFromName('word/styles.xml');
        $outlineCount = 0;

        foreach ([1 => 0, 2 => 1] as $heading => $level) {
            // outlineLvl belongs at the end of pPr in the OOXML sequence, so insert before its closing tag.
            $styles = preg_replace('/(w:styleId="Heading'.$heading.'".*?)(<\/w:pPr>)/s', '$1<w:outlineLvl w:val="'.$level.'"/>$2', $styles, 1, $count);
            $outlineCount += $count;
        }

        // TOC entry styles: bold chapters, indented sections, dot leader to a right tab at the text edge.
        $tab = '<w:tabs><w:tab w:val="right" w:leader="dot" w:pos="'.$textWidth.'"/></w:tabs>';
        $styles = str_replace('</w:styles>',
            '<w:style w:type="paragraph" w:styleId="TOC1"><w:name w:val="toc 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:uiPriority w:val="39"/>'
            .'<w:pPr>'.$tab.'<w:spacing w:before="120"/><w:jc w:val="left"/></w:pPr><w:rPr><w:b/></w:rPr></w:style>'
            .'<w:style w:type="paragraph" w:styleId="TOC2"><w:name w:val="toc 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:uiPriority w:val="39"/>'
            .'<w:pPr>'.$tab.'<w:ind w:left="284"/><w:jc w:val="left"/></w:pPr></w:style></w:styles>',
            $styles, $tocStyleCount);

        if ($romanCount !== 1 || $tocCount !== 1 || $outlineCount !== 2 || $tocStyleCount !== 1 || $breakCount !== $chapterCount) {
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
}
