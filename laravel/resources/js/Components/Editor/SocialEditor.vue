<script setup>
// Editor del testo social (Facebook, Instagram, LinkedIn): testo semplice,
// niente formattazione (i social non la supportano), emoji. Ogni riga è un
// paragrafo; il valore è il testo con gli a capo. I link a un tuo post sono
// chip a video e `[canale url id=N]` nel testo.
import { ref, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import Document from '@tiptap/extension-document';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import { Placeholder, UndoRedo } from '@tiptap/extensions';
import Icon from '@/Components/Icon.vue';
import EmojiButton from '@/Components/Editor/EmojiButton.vue';
import PostLinkPicker from '@/Components/Editor/PostLinkPicker.vue';
import { PostLink } from '@/Components/Editor/shortcodes.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Scrivi il post…' },
    disabled: { type: Boolean, default: false },
    // Account del post: serve al dialog "Un tuo post" (null = pulsante nascosto).
    userId: { type: [Number, String], default: null },
});

const emit = defineEmits(['update:modelValue']);

function escapeHtml(text) {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Testo → paragrafi; gli shortcode dei link a un post tornano chip.
function toDoc(text) {
    return String(text ?? '').split('\n').map((line) => {
        const html = escapeHtml(line).replace(
            /\[(facebook|instagram|linkedin|wordpress|newsletter) url id=(\d+)\]/gi,
            (_, channel, id) => `<span data-post-link data-channel="${channel.toLowerCase()}" data-id="${id}"></span>`,
        );
        return `<p>${html}</p>`;
    }).join('');
}

function toText(editor) {
    return editor.isEmpty ? '' : editor.getText({ blockSeparator: '\n' });
}

const editor = useEditor({
    content: toDoc(props.modelValue),
    editable: !props.disabled,
    extensions: [
        Document,
        Paragraph,
        Text,
        UndoRedo,
        PostLink,
        Placeholder.configure({ placeholder: () => props.placeholder }),
    ],
    onUpdate: ({ editor: e }) => emit('update:modelValue', toText(e)),
});

watch(() => props.modelValue, (value) => {
    const e = editor.value;
    if (e && (value ?? '') !== toText(e)) e.commands.setContent(toDoc(value), { emitUpdate: false });
});

watch(() => props.disabled, (disabled) => editor.value?.setEditable(!disabled));

onBeforeUnmount(() => editor.value?.destroy());

function insertEmoji(emoji) {
    editor.value?.chain().focus().insertContent(emoji).run();
}

const picking = ref(false);

function insertPostLink(post) {
    picking.value = false;
    editor.value?.chain().focus()
        .insertContent([{ type: 'postLink', attrs: { id: post.id, channel: post.channel, title: post.title } }, { type: 'text', text: ' ' }])
        .run();
}
</script>

<template>
    <div class="rte rte--social" :class="{ 'is-disabled': disabled }">
        <div v-if="editor" class="rte-toolbar" role="toolbar" aria-label="Strumenti del testo">
            <EmojiButton :disabled="disabled" @pick="insertEmoji" />
            <button v-if="userId" type="button" class="rte-btn" title="Inserisci il link a un tuo post già pubblicato"
                :disabled="disabled" @click="picking = true"><Icon name="link" :size="16" /></button>
            <slot name="toolbar" />
            <div class="spacer" />
            <button type="button" class="rte-btn" title="Annulla (Ctrl+Z)" :disabled="disabled || !editor.can().undo()"
                @click="editor.chain().focus().undo().run()"><Icon name="undo" :size="16" /></button>
            <button type="button" class="rte-btn" title="Ripeti (Ctrl+Shift+Z)" :disabled="disabled || !editor.can().redo()"
                @click="editor.chain().focus().redo().run()"><Icon name="redo" :size="16" /></button>
        </div>
        <EditorContent :editor="editor" class="rte-content" />
        <PostLinkPicker v-if="picking && userId" :user-id="userId" @pick="insertPostLink" @close="picking = false" />
    </div>
</template>
