import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Pull-to-refresh "da app" su mobile (<= 768px): trascinando verso il basso
 * con la pagina già in cima, oltre la soglia, ricarica le props della pagina
 * corrente con router.reload() di Inertia (niente reload completo, lo stato
 * della pagina resta). Serve soprattutto nella PWA standalone, dove iOS non
 * ha il pull-to-refresh nativo; quello nativo del browser è spento da
 * overscroll-behavior in app.css per non avere due refresh.
 *
 * Non parte con menu aperto (html.sb-lock), con un dialog aperto, da un campo
 * di testo o da un contenitore interno già scrollato. data-no-ptr su un
 * elemento lo esclude esplicitamente.
 */
export function usePullToRefresh({ threshold = 70, max = 110 } = {}) {
    const pull = ref(0);
    const refreshing = ref(false);

    let startY = 0;
    let tracking = false;
    const mobile = typeof window !== 'undefined' ? window.matchMedia('(max-width: 768px)') : null;

    function blocked(target) {
        if (document.documentElement.classList.contains('sb-lock')) return true;
        if (document.querySelector('[aria-modal="true"]')) return true;
        if (!(target instanceof Element)) return false;
        if (target.closest('[data-no-ptr], input, textarea, select, [contenteditable="true"]')) return true;
        for (let el = target; el && el !== document.body; el = el.parentElement) {
            if (el.scrollTop > 0) return true;
        }
        return false;
    }

    function onStart(e) {
        tracking = false;
        if (refreshing.value || !mobile?.matches || e.touches.length !== 1) return;
        if (window.scrollY > 0 || blocked(e.target)) return;
        startY = e.touches[0].clientY;
        tracking = true;
    }

    function onMove(e) {
        if (!tracking) return;
        const dy = e.touches[0].clientY - startY;
        if (dy <= 0 || window.scrollY > 0) {
            pull.value = 0;
            if (window.scrollY > 0) tracking = false;
            return;
        }
        pull.value = Math.min(max, dy * 0.5);
        if (e.cancelable) e.preventDefault();
    }

    function onEnd() {
        if (!tracking) return;
        tracking = false;
        if (pull.value < threshold) {
            pull.value = 0;
            return;
        }
        refreshing.value = true;
        pull.value = threshold;
        router.reload({
            onFinish: () => {
                refreshing.value = false;
                pull.value = 0;
            },
        });
    }

    function onCancel() {
        if (!tracking) return;
        tracking = false;
        pull.value = 0;
    }

    onMounted(() => {
        window.addEventListener('touchstart', onStart, { passive: true });
        window.addEventListener('touchmove', onMove, { passive: false });
        window.addEventListener('touchend', onEnd, { passive: true });
        window.addEventListener('touchcancel', onCancel, { passive: true });
    });

    onUnmounted(() => {
        window.removeEventListener('touchstart', onStart);
        window.removeEventListener('touchmove', onMove);
        window.removeEventListener('touchend', onEnd);
        window.removeEventListener('touchcancel', onCancel);
    });

    return { pull, refreshing, threshold };
}
