import { Editor, Node } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';

// One paragraph per block: Enter cannot split it, so the stored text never contains a newline.
const SingleParagraph = Node.create({ name: 'doc', topNode: true, content: 'paragraph' });
const oneLine = (t) => t.replace(/\s*\n+\s*/g, ' ');

const text = (node) => node.textContent ?? '';

// Plain-text editor per block: no marks, one paragraph <-> text, list items <-> items[].
function mount(el, block) {
    const isList = block.type === 'list';
    const para = (t) => ({ type: 'paragraph', content: t ? [{ type: 'text', text: t }] : [] });
    const content = isList
        ? { type: 'doc', content: [{ type: block.ordered ? 'orderedList' : 'bulletList', content: block.items.map((i) => ({ type: 'listItem', content: [para(i)] })) }] }
        : { type: 'doc', content: [para(oneLine(block.text))] };

    return new Editor({
        element: el,
        content,
        extensions: [
            StarterKit.configure({
                blockquote: false, bold: false, code: false, codeBlock: false, dropcursor: false, gapcursor: false,
                document: isList, hardBreak: false, heading: false, horizontalRule: false, italic: false, link: false, strike: false,
                trailingNode: false, underline: false,
                bulletList: isList, orderedList: isList, listItem: isList, listKeymap: isList,
            }),
            ...(isList ? [] : [SingleParagraph]),
        ],
        editorProps: { transformPastedText: oneLine, attributes: { class: 'outline-none', 'data-test': `block-${block.type}` } },
        onUpdate({ editor }) {
            const nodes = editor.state.doc.content;
            if (!isList) {
                block.text = text(editor.state.doc);
                return;
            }
            const items = [];
            nodes.forEach((n) => (n.type.name === 'paragraph' ? [n] : n.content.content).forEach((i) => items.push(text(i))));
            const kept = items.filter((i) => i !== '');
            block.items = kept.length ? kept : [''];
        },
    });
}

function friendly(errors) {
    const empty = errors.filter((e) => /\/(title|text|items)(\/\d+)?: /.test(e));
    if (!empty.length) return 'Isi dokumen belum valid.';
    return empty.some((e) => /\/title: /.test(e) && !/(least|minimum|empty)/i.test(e))
        ? 'Judul tidak boleh diawali nomor atau kata BAB; penomoran dibuat otomatis.'
        : 'Judul atau teks tidak boleh kosong.';
}

const register = () =>
    window.Alpine.data('docEditor', (initial) => ({
        doc: initial,
        status: 'saved', // saved | saving | error
        errors: [],
        saving: false,
        again: false,
        timer: null,
        editors: [],

        init() {
            this.$watch('doc', () => {
                this.status = 'saving';
                clearTimeout(this.timer);
                this.timer = setTimeout(() => this.flush(), 800);
            });
        },

        mountBlock(el, block) {
            this.editors.push(mount(el, block));
        },

        async flush() {
            if (this.saving) {
                this.again = true;
                return;
            }
            this.saving = true;
            try {
                const errors = await this.$wire.save(JSON.parse(JSON.stringify(this.doc)));
                this.errors = errors.length ? [friendly(errors)] : [];
                if (errors.length) console.warn('Validasi dokumen:', errors);
                else window.dispatchEvent(new CustomEvent('doc-title', { detail: this.doc.cover.title }));
                this.status = errors.length ? 'error' : 'saved';
            } catch (e) {
                this.errors = ['Koneksi bermasalah, perubahan belum tersimpan.'];
                this.status = 'error';
            }
            this.saving = false;
            if (this.again) {
                this.again = false;
                this.flush();
            }
        },

        destroy() {
            this.editors.forEach((e) => e.destroy());
        },
    }));

window.Alpine ? register() : document.addEventListener('alpine:init', register);
