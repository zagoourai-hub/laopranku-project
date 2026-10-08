import { Previewer } from 'pagedjs';

// Paper estimate only: paged.js is not Word/LibreOffice, so the page says "perkiraan".
function pageCss(s) {
    return `
@page { size: A4; margin: ${s.mt}cm ${s.mr}cm ${s.mb}cm ${s.ml}cm; }
@page cover { @bottom-center { content: none; } }
@page front { @bottom-center { content: counter(page, lower-roman); font: ${s.size}pt "${s.font}", serif; } }
@page body { @top-right { content: counter(page); font: ${s.size}pt "${s.font}", serif; } }
@page body:first { @top-right { content: none; } @bottom-center { content: counter(page); font: ${s.size}pt "${s.font}", serif; } }
section.cover { page: cover; }
section.front { page: front; break-before: page; counter-reset: page 1; }
section.body { page: body; break-before: page; }
section.body.first { counter-reset: page 1; }
h1, h2 { break-after: avoid; }
h1.new-page { break-before: page; }
/* Paged.js injects this sheet into <head>, so every visual rule is scoped to the paper, never the app. */
.pagedjs_page_content { font-family: "${s.font}", "Times New Roman", serif; font-size: ${s.size}pt; line-height: ${s.line}; color: #000; text-align: justify; }
/* paged.js marks a section cut by a page break with text-align-last: justify; stop it leaking into headings and paragraphs inside. */
.pagedjs_page_content [data-align-last-split-element] :not([data-align-last-split-element]) { text-align-last: auto; }
.pagedjs_page_content section.cover { text-align: center; }
.pagedjs_page_content .cover-type { margin-top: 2em; font-size: 14pt; font-weight: bold; }
.pagedjs_page_content .cover-title { margin: 1em 0 6em; font-size: 14pt; font-weight: bold; }
.pagedjs_page_content .cover-foot { margin-top: 9em; }
.pagedjs_page_content h1 { text-align: center; font-size: ${s.size}pt; font-weight: bold; margin: 0 0 24pt; }
.pagedjs_page_content p.signature { text-align: right; margin-top: 1.5em; }
.pagedjs_page_content h2 { text-align: left; font-size: ${s.size}pt; font-weight: bold; margin: 12pt 0 0; }
.pagedjs_page_content p { margin: 0; }
.pagedjs_page_content p.indent { text-indent: ${s.indent}cm; }
.pagedjs_page_content p.ref { padding-left: ${s.hanging}cm; text-indent: -${s.hanging}cm; margin-bottom: 6pt; }
.pagedjs_page_content ol, .pagedjs_page_content ul { margin: 0; padding-left: 1.27cm; }
.pagedjs_page_content ol { list-style: decimal; }
.pagedjs_page_content ul { list-style: disc; }
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
        previewer: null,
        fit: null,

        init() {
            const source = document.getElementById('preview-source');
            const target = document.getElementById('preview-pages');

            if (!source || !target) return;

            this.previewer = new Previewer();
            this.previewer
                .preview(source.innerHTML, [{ 'preview.css': pageCss(source.dataset) }], target)
                .finally(() => {
                    this.rendering = false;
                    this.fit = () => this.scale(target);
                    this.$nextTick(this.fit);
                    window.addEventListener('resize', this.fit);
                });
        },

        // Shrink the A4 sheets to the container on narrow screens; zoom keeps layout honest (no clipped text, no blank gap).
        scale(target) {
            const pages = target.querySelector('.pagedjs_pages');
            const page = target.querySelector('.pagedjs_page');
            if (!pages || !page) return;
            pages.style.zoom = '';
            const room = target.clientWidth - 32;
            pages.style.zoom = Math.min(1, room / page.offsetWidth);
        },

        // wire:navigate swaps the page without a reload, so drop paged.js's <head> styles with the component.
        destroy() {
            window.removeEventListener('resize', this.fit);
            this.previewer?.polisher?.destroy();
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
