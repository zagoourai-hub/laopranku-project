import { Editor, Node } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Sortable from 'sortablejs';

const MAX_HISTORY = 100;

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
                trailingNode: false, underline: false, undoRedo: false, // one history for the whole document, see docEditor
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
        rev: 0, // bumped by undo/redo so every block remounts on the restored objects
        past: [],
        future: [],
        last: JSON.stringify(initial),
        dirty: false,

        init() {
            this.$watch('doc', () => {
                this.status = 'saving';
                this.dirty = true;
                clearTimeout(this.timer);
                this.timer = setTimeout(() => {
                    this.settle();
                    this.flush();
                }, 800);
            });
        },

        get canUndo() {
            return this.dirty || this.past.length > 0;
        },

        get canRedo() {
            return this.future.length > 0;
        },

        // Display-only chapter number (BAB I, II, ...); the builder numbers the real document.
        roman(n) {
            return [[10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']].reduce((out, [v, s]) => {
                for (; n >= v; n -= v) out += s;
                return out;
            }, '');
        },

        // Commit pending edits as one history step (snapshots are JSON strings, so no shared references).
        settle() {
            clearTimeout(this.timer);
            this.dirty = false;
            const now = JSON.stringify(this.doc);
            if (now === this.last) return;
            this.past.push(this.last);
            if (this.past.length > MAX_HISTORY) this.past.shift();
            this.future = [];
            this.last = now;
        },

        restore(snapshot) {
            this.editors.forEach((e) => e.destroy());
            this.editors = [];
            this.rev++;
            this.last = snapshot;
            this.doc = JSON.parse(snapshot);
        },

        undo() {
            this.settle();
            if (!this.past.length) return;
            this.future.push(this.last);
            this.restore(this.past.pop());
        },

        redo() {
            this.settle();
            if (!this.future.length) return;
            this.past.push(this.last);
            this.restore(this.future.pop());
        },

        keys(e) {
            if (!(e.ctrlKey || e.metaKey) || e.altKey) return;
            const k = e.key.toLowerCase();
            if (k !== 'z' && k !== 'y') return;
            e.preventDefault();
            k === 'y' || e.shiftKey ? this.redo() : this.undo();
        },

        // Move one item in a list (chapters or a chapter's sections); each move is its own undo step.
        reorder(list, from, to) {
            const arr = list();
            if (to < 0 || to >= arr.length || from === to) return;
            this.settle();
            arr.splice(to, 0, arr.splice(from, 1)[0]);
            this.settle();
        },

        // SortableJS only reports the drop. Put its DOM move back and let Alpine's keyed x-for do the real move,
        // so the TipTap editors inside are never duplicated or orphaned.
        sortable(el, list, handle) {
            Sortable.create(el, {
                handle,
                draggable: '.js-sort-item',
                animation: 150,
                onEnd: ({ item, from, oldIndex, newIndex }) => {
                    from.removeChild(item);
                    const rest = [...from.children].filter((c) => c.classList.contains('js-sort-item'));
                    from.insertBefore(item, rest[oldIndex] ?? null);
                    this.reorder(list, oldIndex, newIndex);
                },
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
