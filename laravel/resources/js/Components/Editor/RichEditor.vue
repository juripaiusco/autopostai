<script setup>
// Editor ricco del testo articolo (WordPress, Newsletter). Produce solo ciò
// che il server lascia passare (App\Services\PostHtmlSanitizer): H2/H3,
// grassetto, corsivo, sottolineato, elenchi, citazione, link, colore del
// testo da palette e allineamento. Il titolo (H1) è un campo a parte.
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { TextStyle, Color } from '@tiptap/extension-text-style';
import TextAlign from '@tiptap/extension-text-align';
import { Placeholder } from '@tiptap/extensions';
import Icon from '@/Components/Icon.vue';
import EmojiButton from '@/Components/Editor/EmojiButton.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Scrivi il testo…' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

// Colori leggibili su fondo bianco, sia nelle email che sui temi WordPress.
const PALETTE = [
    { value: '#1f2937', label: 'Antracite' },
    { value: '#6b7280', label: 'Grigio' },
    { value: '#c0392b', label: 'Rosso' },
    { value: '#d35400', label: 'Arancione' },
    { value: '#9a7d0a', label: 'Ocra' },
    { value: '#1e8449', label: 'Verde' },
    { value: '#1f618d', label: 'Blu' },
    { value: '#7d3c98', label: 'Viola' },
];

const editor = useEditor({
    content: props.modelValue,
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            code: false,
            codeBlock: false,
            strike: false,
            link: {
                openOnClick: false,
                autolink: true,
                defaultProtocol: 'https',
                protocols: ['mailto', 'tel'],
                HTMLAttributes: { target: '_blank', rel: null },
            },
        }),
        TextStyle,
        Color,
        TextAlign.configure({ types: ['heading', 'paragraph'] }),
        Placeholder.configure({ placeholder: () => props.placeholder }),
    ],
    onUpdate: ({ editor: e }) => {
        emit('update:modelValue', e.isEmpty ? '' : e.getHTML());
    },
});

// Testo sostituito da fuori (nuova anteprima AI): l'editor si riallinea.
watch(() => props.modelValue, (value) => {
    const e = editor.value;
    if (!e) return;
    const current = e.isEmpty ? '' : e.getHTML();
    if (value !== current) e.commands.setContent(value || '', { emitUpdate: false });
});

watch(() => props.disabled, (disabled) => editor.value?.setEditable(!disabled));

onBeforeUnmount(() => editor.value?.destroy());

const popover = ref(null); // 'link' | 'color' | null
const linkUrl = ref('');

function toggle(name) {
    if (popover.value === name) {
        popover.value = null;
        return;
    }
    if (name === 'link') linkUrl.value = editor.value?.getAttributes('link').href ?? '';
    popover.value = name;
}

function applyLink() {
    const e = editor.value;
    let url = linkUrl.value.trim();
    if (!e) return;
    if (!url) {
        e.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        if (!/^(https?:|mailto:|tel:|\[)/i.test(url)) url = `https://${url}`;
        if (e.state.selection.empty && !e.isActive('link')) {
            e.chain().focus().insertContent({ type: 'text', text: url, marks: [{ type: 'link', attrs: { href: url } }] }).run();
        } else {
            e.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        }
    }
    popover.value = null;
}

function removeLink() {
    editor.value?.chain().focus().extendMarkRange('link').unsetLink().run();
    popover.value = null;
}

function setColor(value) {
    const chain = editor.value?.chain().focus();
    if (!chain) return;
    (value ? chain.setColor(value) : chain.unsetColor()).run();
    popover.value = null;
}

function blockType() {
    const e = editor.value;
    if (e?.isActive('heading', { level: 2 })) return 'h2';
    if (e?.isActive('heading', { level: 3 })) return 'h3';
    return 'p';
}

function setBlock(type) {
    const chain = editor.value?.chain().focus();
    if (!chain) return;
    if (type === 'p') chain.setParagraph().run();
    else chain.setHeading({ level: type === 'h2' ? 2 : 3 }).run();
}
</script>

<template>
    <div class="rte" :class="{ 'is-disabled': disabled }">
        <div v-if="editor" class="rte-toolbar" role="toolbar" aria-label="Formattazione del testo">
            <select class="rte-select" :value="blockType()" aria-label="Stile del paragrafo" :disabled="disabled"
                @change="setBlock($event.target.value)">
                <option value="p">Testo</option>
                <option value="h2">Titoletto</option>
                <option value="h3">Sottotitoletto</option>
            </select>
            <span class="rte-sep" />
            <button type="button" class="rte-btn rte-btn--b" :class="{ 'is-active': editor.isActive('bold') }" title="Grassetto (Ctrl+B)"
                :disabled="disabled" @click="editor.chain().focus().toggleBold().run()">B</button>
            <button type="button" class="rte-btn rte-btn--i" :class="{ 'is-active': editor.isActive('italic') }" title="Corsivo (Ctrl+I)"
                :disabled="disabled" @click="editor.chain().focus().toggleItalic().run()">I</button>
            <button type="button" class="rte-btn rte-btn--u" :class="{ 'is-active': editor.isActive('underline') }" title="Sottolineato (Ctrl+U)"
                :disabled="disabled" @click="editor.chain().focus().toggleUnderline().run()">U</button>
            <div class="rte-pop-wrap">
                <button type="button" class="rte-btn rte-btn--color" :class="{ 'is-active': popover === 'color' }" title="Colore del testo"
                    :disabled="disabled" @click="toggle('color')">
                    A<span class="rte-color-bar" :style="{ background: editor.getAttributes('textStyle').color || 'var(--ink)' }" />
                </button>
                <div v-if="popover === 'color'" class="rte-pop rte-pop--colors">
                    <button v-for="c in PALETTE" :key="c.value" type="button" class="rte-swatch" :title="c.label"
                        :style="{ background: c.value }" @click="setColor(c.value)" />
                    <button type="button" class="rte-pop-link" @click="setColor(null)">Colore normale</button>
                </div>
            </div>
            <span class="rte-sep" />
            <button type="button" class="rte-btn" :class="{ 'is-active': editor.isActive('bulletList') }" title="Elenco puntato"
                :disabled="disabled" @click="editor.chain().focus().toggleBulletList().run()"><Icon name="listBullet" :size="17" /></button>
            <button type="button" class="rte-btn" :class="{ 'is-active': editor.isActive('orderedList') }" title="Elenco numerato"
                :disabled="disabled" @click="editor.chain().focus().toggleOrderedList().run()"><Icon name="listNumbered" :size="17" /></button>
            <button type="button" class="rte-btn rte-btn--quote" :class="{ 'is-active': editor.isActive('blockquote') }" title="Citazione"
                :disabled="disabled" @click="editor.chain().focus().toggleBlockquote().run()">❝</button>
            <span class="rte-sep" />
            <button type="button" class="rte-btn" :class="{ 'is-active': editor.isActive({ textAlign: 'left' }) }" title="Allinea a sinistra"
                :disabled="disabled" @click="editor.chain().focus().setTextAlign('left').run()"><Icon name="alignLeft" :size="17" /></button>
            <button type="button" class="rte-btn" :class="{ 'is-active': editor.isActive({ textAlign: 'center' }) }" title="Centra"
                :disabled="disabled" @click="editor.chain().focus().setTextAlign('center').run()"><Icon name="alignCenter" :size="17" /></button>
            <button type="button" class="rte-btn" :class="{ 'is-active': editor.isActive({ textAlign: 'right' }) }" title="Allinea a destra"
                :disabled="disabled" @click="editor.chain().focus().setTextAlign('right').run()"><Icon name="alignRight" :size="17" /></button>
            <span class="rte-sep" />
            <div class="rte-pop-wrap">
                <button type="button" class="rte-btn" :class="{ 'is-active': editor.isActive('link') || popover === 'link' }" title="Link"
                    :disabled="disabled" @click="toggle('link')"><Icon name="link" :size="16" /></button>
                <form v-if="popover === 'link'" class="rte-pop rte-pop--link" @submit.prevent="applyLink">
                    <input v-model="linkUrl" class="control" type="text" placeholder="https://…" aria-label="Indirizzo del link" autofocus />
                    <button type="submit" class="btn btn-dark btn-sm">Applica</button>
                    <button v-if="editor.isActive('link')" type="button" class="btn btn-secondary btn-sm" @click="removeLink">Rimuovi</button>
                </form>
            </div>
            <EmojiButton :disabled="disabled" @pick="editor.chain().focus().insertContent($event).run()" />
            <div class="spacer" />
            <button type="button" class="rte-btn" title="Annulla (Ctrl+Z)" :disabled="disabled || !editor.can().undo()"
                @click="editor.chain().focus().undo().run()"><Icon name="undo" :size="16" /></button>
            <button type="button" class="rte-btn" title="Ripeti (Ctrl+Shift+Z)" :disabled="disabled || !editor.can().redo()"
                @click="editor.chain().focus().redo().run()"><Icon name="redo" :size="16" /></button>
        </div>
        <EditorContent :editor="editor" class="rte-content" />
    </div>
</template>
