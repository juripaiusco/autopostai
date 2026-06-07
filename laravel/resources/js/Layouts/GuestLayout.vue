<script setup>
// Asset statico servito da public/ (binding runtime: niente trasformazione
// asset di Vite, l'URL resta /images/... in dev e in produzione).
const logo = '/images/faper3-logo.png';
</script>

<template>
    <div class="auth-page">
        <div class="logo-stack">
            <span class="brandmark">
                <img :src="logo" alt="FaPer3" width="84" height="84" />
            </span>
        </div>
        <slot />
    </div>
</template>

<style scoped>
/* Shell pagina auth — layout "centrata" del design FaPer3 */
.auth-page {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 80px 20px 40px;
    background: var(--bg-canvas);
}
.logo-stack {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-bottom: 30px;
}
.brandmark {
    display: block;
}
.brandmark img {
    display: block;
}
</style>
