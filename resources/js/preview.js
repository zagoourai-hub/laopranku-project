import { Previewer } from 'pagedjs';

// Paper estimate only: paged.js is not Word/LibreOffice, so the page says "perkiraan".
function pageCss(s) {
    return `
@page { size: A4; margin: ${s.mt}cm ${s.mr}cm ${s.mb}cm ${s.ml}cm; }
@page cover { @bottom-center { content: none; } }
@page front { @bottom-center { content: counter(page, lower-roman); font: ${s.size}pt "${s.font}", serif; } }
@page body { @top-right { content: counter(page); font: ${s.size}pt "${s.font}", serif; } }
@page body:first { @top-right { content: none; } @bottom-center { content: counter(page); font: ${s.size}pt "${s.font}", serif; } }
section.cover { page: cover; text-align: center; }
section.front { page: front; break-before: page; counter-reset: page 1; }
section.body { page: body; break-before: page; }
section.body.first { counter-reset: page 1; }
body, .pagedjs_page_content { font-family: "${s.font}", "Times New Roman", serif; font-size: ${s.size}pt; line-height: ${s.line}; color: #000; text-align: justify; }
.cover-type { margin-top: 2em; font-size: 14pt; font-weight: bold; }
.cover-title { margin: 1em 0 6em; font-size: 14pt; font-weight: bold; }
.cover-foot { margin-top: 9em; }
h1 { text-align: center; font-size: ${s.size}pt; font-weight: bold; margin: 0 0 24pt; break-after: avoid; }
h1.new-page { break-before: page; }
p.signature { text-align: right; margin-top: 1.5em; }
h2 { text-align: left; font-size: ${s.size}pt; font-weight: bold; margin: 12pt 0 0; break-after: avoid; }
p { margin: 0; }
p.indent { text-indent: ${s.indent}cm; }
p.ref { padding-left: ${s.hanging}cm; text-indent: -${s.hanging}cm; margin-bottom: 6pt; }
ol, ul { margin: 0; padding-left: 1.27cm; }
ol { list-style: decimal; }
ul { list-style: disc; }
ul.toc { list-style: none; padding: 0; text-align: left; }
ul.toc a { color: inherit; text-decoration: none; }
ul.toc li { display: flex; }
ul.toc a { display: flex; flex: 1; align-items: baseline; }
ul.toc a::before { content: ""; order: 1; flex: 1; border-bottom: 1px dotted #000; margin: 0 4px; }
ul.toc a[href]::after { content: target-counter(attr(href url), page); order: 2; }
li.toc-1 { font-weight: bold; margin-top: 6pt; }
li.toc-front a[href]::after { content: target-counter(attr(href url), page, lower-roman); }
li.toc-2 { padding-left: 0.5cm; }
`;
}

function register() {
    window.Alpine.data('docPreview', () => ({
        rendering: true,
        loading: false,
        pdf: null,
        error: null,

        init() {
            const source = document.getElementById('preview-source');
            const target = document.getElementById('preview-pages');

            if (!source || !target) return;

            new Previewer()
                .preview(source.innerHTML, [{ 'preview.css': pageCss(source.dataset) }], target)
                .finally(() => { this.rendering = false; });
        },

        async open() {
            this.loading = true;
            this.error = null;

            try {
                const res = await fetch(await this.$wire.accurateUrl(), { credentials: 'same-origin' });

                if (!res.ok) {
                    this.error = res.status === 503
                        ? 'Konversi PDF sedang tidak tersedia. Coba lagi sebentar lagi.'
                        : 'Preview akurat gagal dibuat. Pastikan dokumen punya isi yang valid.';

                    return;
                }

                this.close();
                this.pdf = URL.createObjectURL(await res.blob());
            } catch {
                this.error = 'Koneksi bermasalah. Coba lagi.';
            } finally {
                this.loading = false;
            }
        },

        close() {
            if (this.pdf) URL.revokeObjectURL(this.pdf);
            this.pdf = null;
        },
    }));
}

window.Alpine ? register() : document.addEventListener('alpine:init', register);
