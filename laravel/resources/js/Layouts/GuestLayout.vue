<script setup>
// Asset statico servito da public/ (binding runtime: niente trasformazione
// asset di Vite, l'URL resta /images/... in dev e in produzione).
const logo = '/images/faper3-logo.png';
</script>

<template>
    <div class="auth-split">
        <aside class="auth-split__brand">
            <div class="brand-logo-wrap">
                <span class="brandmark">
                    <img :src="logo" alt="FaPer3" width="56" height="56" />
                </span>
            </div>
            <div class="brand-copy">
                <h2 class="brand-headline">Comunica come un <span class="hl">professionista.</span></h2>
                <p class="brand-sub">
                    Scrivi una volta, lascia che l'AI adatti e pubblichi il tuo post su tutti i canali.
                    FaPer3 fa il lavoro di tre.
                </p>
                <div class="brand-channels" aria-hidden="true">
                    <i><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.84c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94Z"/></svg></i>
                    <i><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg></i>
                    <i><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M6.94 8.5H3.56V21h3.38V8.5ZM5.25 3a1.97 1.97 0 1 0 0 3.94A1.97 1.97 0 0 0 5.25 3ZM21 21v-6.86c0-3.27-1.75-4.79-4.08-4.79-1.88 0-2.72 1.04-3.19 1.76V8.5H10.4c.05.96 0 12.5 0 12.5h3.33v-6.98c0-.37.03-.75.14-1.02.3-.75 1-1.53 2.16-1.53 1.52 0 2.13 1.16 2.13 2.86V21H21Z"/></svg></i>
                    <i><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-7.94 8.7 3.36 9.2a8.1 8.1 0 0 1-3.36-9.2Zm7.94-6.84c1.5 0 2.86.34 4.05.95a.74.74 0 0 0-.08 0c-.78 0-1.34.68-1.34 1.4 0 .65.37 1.2.77 1.85.3.53.65 1.2.65 2.18 0 .67-.26 1.46-.6 2.55l-.78 2.62-2.86-8.5a.94.94 0 0 1 .8-.4c.06 0 .12 0 .14-.01a.32.32 0 0 0 .27-.32c0-.18-.16-.32-.36-.31-.6.04-1.49.04-1.49.04s-.93 0-1.55-.04a.34.34 0 0 0-.36.32c0 .16.12.3.27.31.06.01.12.01.18.02.6.07.65.08.94.93l1.3 3.46-1.83 5.49-3.05-8.95c.6-.07.65-.08.65-.34a.32.32 0 0 0-.34-.31s-1.16.09-1.91.09c-.13 0-.29 0-.46-.01A8.16 8.16 0 0 1 12 3.86Zm.51 14.5 2.61-7.32 2.65 6.96a.4.4 0 0 0 .03.06 8.13 8.13 0 0 1-5.29.3Zm6.79-1.92 2.69-7.66.04-.16a8.16 8.16 0 0 1-2.73 7.82Z"/></svg></i>
                    <i><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></i>
                </div>
            </div>
            <div class="brand-foot">© 2026 FaPer3 — Gestisci la tua comunicazione online come un professionista.</div>
        </aside>
        <div class="auth-split__form">
            <div class="mobile-logo">
                <span class="brandmark">
                    <img :src="logo" alt="FaPer3" width="72" height="72" />
                </span>
            </div>
            <slot />
        </div>
    </div>
</template>

<style scoped>
/* Shell pagina auth — layout "split" del design FaPer3 */
.auth-split {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 1.05fr 1fr;
    background: var(--bg-surface);
}
.auth-split__brand {
    position: relative;
    background: var(--ink);
    color: var(--fg-on-dark);
    padding: 56px 64px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
}
.auth-split__brand::after {
    content: "";
    position: absolute;
    right: -120px;
    bottom: -140px;
    width: 460px;
    height: 460px;
    border-radius: 50%;
    background: radial-gradient(circle at 30% 30%, rgba(56, 189, 248, .40), rgba(56, 189, 248, 0) 70%);
    pointer-events: none;
}
.auth-split__form {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    background: var(--bg-canvas);
}

.brandmark {
    display: block;
}
.brandmark img {
    display: block;
}
.brand-logo-wrap,
.brand-copy,
.brand-foot {
    position: relative;
    z-index: 1;
}
.brand-headline {
    font-size: clamp(1.9rem, 3vw, 2.6rem);
    font-weight: var(--w-extrabold);
    line-height: 1.12;
    letter-spacing: -0.02em;
    margin: 18px 0 0;
    max-width: 13ch;
    color: var(--fg-on-dark);
}
.brand-headline .hl {
    color: var(--sky);
}
.brand-sub {
    margin: 18px 0 0;
    font-size: var(--text-base);
    line-height: 1.6;
    color: color-mix(in srgb, #fff 72%, transparent);
    max-width: 34ch;
}
.brand-channels {
    display: flex;
    gap: 12px;
    margin-top: 30px;
}
.brand-channels i {
    width: 42px;
    height: 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius);
    background: rgba(255, 255, 255, .08);
    color: #fff;
}
.brand-foot {
    font-size: var(--text-xs);
    color: color-mix(in srgb, #fff 50%, transparent);
}

.mobile-logo {
    display: none;
    flex-direction: column;
    align-items: center;
    margin-bottom: 28px;
}

@media (max-width: 860px) {
    .auth-split {
        grid-template-columns: 1fr;
    }
    .auth-split__brand {
        display: none;
    }
    .auth-split__form {
        background: var(--bg-canvas);
        padding: 48px 20px 40px;
    }
    .mobile-logo {
        display: flex;
    }
}
</style>
