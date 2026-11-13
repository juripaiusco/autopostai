<script setup>
/**
 * Anteprima dell'email in un iframe isolato: il CSS dell'email non tocca
 * l'app e viceversa. sandbox senza allow-scripts: nessuno script dell'HTML
 * gira; allow-same-origin serve solo a caricare le immagini appena scelte
 * (URL blob: della pagina).
 */
defineProps({
    html: { type: String, default: '' },
    height: { type: Number, default: 560 },
});
</script>

<template>
    <iframe class="pf-nl-preview" title="Anteprima email" sandbox="allow-same-origin"
        :srcdoc="html" :style="{ height: height + 'px' }"></iframe>
</template>

<style scoped>
.pf-nl-preview {
    /* Anteprima email: resta chiara anche col tema scuro (il client email non segue l'app). */
    color-scheme: light;
    display: block;
    width: 100%;
    border: 1px solid var(--border, #e5e7eb);
    border-radius: 10px;
    background: #fff;
}
</style>
