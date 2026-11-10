<script setup>
// Editor del testo social (Facebook, Instagram, LinkedIn): testo semplice,
// niente formattazione (i social non la supportano), emoji. Ogni riga è un
// paragrafo; il valore è il testo con gli a capo.
import { watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import Document from '@tiptap/extension-document';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';
import { Placeholder, UndoRedo } from '@tiptap/extensions';
import Icon from '@/Components/Icon.vue';
import EmojiButton from '@/Components/Editor/EmojiButton.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Scrivi il post…' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function escapeHtml(text) {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function toDoc(text) {
    return String(text ?? '').split('\n').map((line) => `<p>${escapeHtml(line)}</p>`).join('');
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
</script>

<template>
    <div class="rte rte--social" :class="{ 'is-disabled': disabled }">
        <div v-if="editor" class="rte-toolbar" role="toolbar" aria-label="Strumenti del testo">
            <EmojiButton :disabled="disabled" @pick="insertEmoji" />
            <slot name="toolbar" />
            <div class="spacer" />
            <button type="button" class="rte-btn" title="Annulla (Ctrl+Z)" :disabled="disabled || !editor.can().undo()"
                @click="editor.chain().focus().undo().run()"><Icon name="undo" :size="16" /></button>
            <button type="button" class="rte-btn" title="Ripeti (Ctrl+Shift+Z)" :disabled="disabled || !editor.can().redo()"
                @click="editor.chain().focus().redo().run()"><Icon name="redo" :size="16" /></button>
        </div>
        <EditorContent :editor="editor" class="rte-content" />
    </div>
</template>
