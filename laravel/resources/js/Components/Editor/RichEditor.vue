<script setup>
// Editor ricco del testo articolo (WordPress, Newsletter). Produce solo ciò
// che il server lascia passare (App\Services\PostHtmlSanitizer): H2/H3,
// grassetto, corsivo, sottolineato, elenchi, citazione, link, colore del
// testo da palette, allineamento e immagini (solo URL http/https: arrivano
// dall'AI, che le prende dalle pagine linkate nel prompt; niente pulsante di
// inserimento). Il titolo (H1) è un campo a parte.
// Shortcode senza sintassi: link a un tuo post e pulsante CTA (newsletter).
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { TextStyle, Color } from '@tiptap/extension-text-style';
import TextAlign from '@tiptap/extension-text-align';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';
import Icon from '@/Components/Icon.vue';
import EmojiButton from '@/Components/Editor/EmojiButton.vue';
import PostLinkPicker from '@/Components/Editor/PostLinkPicker.vue';
import { CtaButton, postLinkShortcode, describePostLink } from '@/Components/Editor/shortcodes.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Scrivi il testo…' },
    disabled: { type: Boolean, default: false },
    // Account del post: serve al dialog "Un tuo post" (null = dialog nascosto).
    userId: { type: [Number, String], default: null },
    // Pulsante CTA: solo con la newsletter accesa (template CTA dell'account).
    allowCta: { type: Boolean, default: false },
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
        Image.configure({ inline: false, allowBase64: false }),
        Placeholder.configure({ placeholder: () => props.placeholder }),
        CtaButton,
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

const popover = ref(null); // 'link' | 'color' | 'cta' | null
const linkUrl = ref('');
const pickerFor = ref(null); // 'link' | 'cta' | null: dialog "Un tuo post" aperto
const linkedPost = computed(() => describePostLink(linkUrl.value));

const ctaText = ref('');
const ctaTarget = ref('url'); // 'url' | 'post'
const ctaUrl = ref('');
const ctaPost = ref(null); // { id, title, channel, label }

function toggle(name) {
    if (popover.value === name) {
        popover.value = null;
        return;
    }
    if (name === 'link') linkUrl.value = editor.value?.getAttributes('link').href ?? '';
    if (name === 'cta') {
        ctaText.value = 'Scopri di più';
        ctaTarget.value = 'url';
        ctaUrl.value = '';
        ctaPost.value = null;
    }
    popover.value = name;
}

function setLinkHref(href, text) {
    const e = editor.value;
    if (!e) return;
    if (e.state.selection.empty && !e.isActive('link')) {
        e.chain().focus().insertContent({ type: 'text', text, marks: [{ type: 'link', attrs: { href } }] }).run();
    } else {
        e.chain().focus().extendMarkRange('link').setLink({ href }).run();
    }
}

function onPostPicked(post) {
    const target = pickerFor.value;
    pickerFor.value = null;
    if (target === 'link') {
        setLinkHref(postLinkShortcode(post.channel, post.id), post.title);
        popover.value = null;
    } else {
        ctaPost.value = post;
        ctaTarget.value = 'post';
    }
}

const canInsertCta = computed(() => ctaText.value.trim() !== ''
    && (ctaTarget.value === 'post' ? !!ctaPost.value : ctaUrl.value.trim() !== ''));

function insertCta() {
    if (!canInsertCta.value) return;
    let url = ctaUrl.value.trim();
    if (ctaTarget.value === 'url' && !/^(https?:|mailto:|tel:)/i.test(url)) url = `https://${url}`;
    const attrs = ctaTarget.value === 'post'
        ? { text: ctaText.value, channel: ctaPost.value.channel, postId: ctaPost.value.id, title: ctaPost.value.title }
        : { text: ctaText.value, url };
    editor.value?.chain().focus().insertContent({ type: 'ctaButton', attrs }).run();
    popover.value = null;
}

function applyLink() {
    const e = editor.value;
    let url = linkUrl.value.trim();
    if (!e) return;
    if (!url) {
        e.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        if (!/^(https?:|mailto:|tel:|\[)/i.test(url)) url = `https://${url}`;
        setLinkHref(url, url);
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
                    <div v-if="linkedPost" class="rte-pop-info">🔗 Collegato a: <strong>{{ linkedPost }}</strong></div>
                    <div class="rte-pop-row">
                        <input v-model="linkUrl" class="control" type="text" placeholder="https://…" aria-label="Indirizzo del link" autofocus />
                        <button type="submit" class="btn btn-dark btn-sm">Applica</button>
                        <button v-if="editor.isActive('link')" type="button" class="btn btn-secondary btn-sm" @click="removeLink">Rimuovi</button>
                    </div>
                    <button v-if="userId" type="button" class="rte-pop-link" @click="pickerFor = 'link'">…oppure collega un tuo post già pubblicato</button>
                </form>
            </div>
            <div v-if="allowCta" class="rte-pop-wrap">
                <button type="button" class="rte-btn rte-btn--cta" :class="{ 'is-active': popover === 'cta' }"
                    title="Pulsante call-to-action (newsletter)" :disabled="disabled" @click="toggle('cta')">CTA</button>
                <form v-if="popover === 'cta'" class="rte-pop rte-pop--cta" @submit.prevent="insertCta">
                    <div class="rte-pop-title">Pulsante nella newsletter</div>
                    <label class="rte-pop-label" for="cta-text">Testo del pulsante</label>
                    <input id="cta-text" v-model="ctaText" class="control" type="text" maxlength="60" />
                    <span class="rte-pop-label">Porta a</span>
                    <label class="rte-radio"><input v-model="ctaTarget" type="radio" value="url" /> un indirizzo web</label>
                    <input v-if="ctaTarget === 'url'" v-model="ctaUrl" class="control" type="text" placeholder="https://…" aria-label="Indirizzo del pulsante" />
                    <label v-if="userId" class="rte-radio"><input v-model="ctaTarget" type="radio" value="post" /> un tuo post già pubblicato</label>
                    <div v-if="ctaTarget === 'post'" class="rte-pop-row">
                        <span v-if="ctaPost" class="rte-pop-info" style="flex:1">🔗 {{ ctaPost.title }} · {{ ctaPost.label }}</span>
                        <button type="button" class="btn btn-secondary btn-sm" @click="pickerFor = 'cta'">{{ ctaPost ? 'Cambia' : 'Scegli il post' }}</button>
                    </div>
                    <p class="rte-pop-hint">Lo stile del pulsante è quello impostato in Account → Newsletter. Su WordPress diventa un link.</p>
                    <div class="rte-pop-row" style="justify-content:flex-end">
                        <button type="button" class="btn btn-secondary btn-sm" @click="popover = null">Annulla</button>
                        <button type="submit" class="btn btn-dark btn-sm" :disabled="!canInsertCta">Inserisci</button>
                    </div>
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
        <PostLinkPicker v-if="pickerFor && userId" :user-id="userId"
            :title="pickerFor === 'cta' ? 'Il pulsante porta a un tuo post' : 'Collega un tuo post'"
            @pick="onPostPicked" @close="pickerFor = null" />
    </div>
</template>
