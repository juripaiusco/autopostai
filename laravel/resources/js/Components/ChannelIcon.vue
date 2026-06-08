<script setup>
// Brand glyphs reused from GuestLayout.vue's .brand-channels strip — kept as
// inline SVG (no Font Awesome dependency) so they match the rest of the app.
const CHANNELS = {
    facebook: {
        label: 'Facebook',
        fill: true,
        path: 'M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.84c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94Z',
    },
    instagram: {
        label: 'Instagram',
        stroke: true,
        shape: 'instagram',
    },
    linkedin: {
        label: 'LinkedIn',
        fill: true,
        path: 'M6.94 8.5H3.56V21h3.38V8.5ZM5.25 3a1.97 1.97 0 1 0 0 3.94A1.97 1.97 0 0 0 5.25 3ZM21 21v-6.86c0-3.27-1.75-4.79-4.08-4.79-1.88 0-2.72 1.04-3.19 1.76V8.5H10.4c.05.96 0 12.5 0 12.5h3.33v-6.98c0-.37.03-.75.14-1.02.3-.75 1-1.53 2.16-1.53 1.52 0 2.13 1.16 2.13 2.86V21H21Z',
    },
    wordpress: {
        label: 'WordPress',
        fill: true,
        path: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-7.94 8.7 3.36 9.2a8.1 8.1 0 0 1-3.36-9.2Zm7.94-6.84c1.5 0 2.86.34 4.05.95a.74.74 0 0 0-.08 0c-.78 0-1.34.68-1.34 1.4 0 .65.37 1.2.77 1.85.3.53.65 1.2.65 2.18 0 .67-.26 1.46-.6 2.55l-.78 2.62-2.86-8.5a.94.94 0 0 1 .8-.4c.06 0 .12 0 .14-.01a.32.32 0 0 0 .27-.32c0-.18-.16-.32-.36-.31-.6.04-1.49.04-1.49.04s-.93 0-1.55-.04a.34.34 0 0 0-.36.32c0 .16.12.3.27.31.06.01.12.01.18.02.6.07.65.08.94.93l1.3 3.46-1.83 5.49-3.05-8.95c.6-.07.65-.08.65-.34a.32.32 0 0 0-.34-.31s-1.16.09-1.91.09c-.13 0-.29 0-.46-.01A8.16 8.16 0 0 1 12 3.86Zm.51 14.5 2.61-7.32 2.65 6.96a.4.4 0 0 0 .03.06 8.13 8.13 0 0 1-5.29.3Zm6.79-1.92 2.69-7.66.04-.16a8.16 8.16 0 0 1-2.73 7.82Z',
    },
    newsletter: {
        label: 'Newsletter',
        shape: 'mail',
    },
};

defineProps({
    id: { type: String, required: true },
    size: { type: [Number, String], default: 16 },
});
</script>

<template>
    <svg
        v-if="CHANNELS[id]"
        width="18"
        height="18"
        viewBox="0 0 24 24"
        :style="{ width: size + 'px', height: size + 'px', display: 'block' }"
        :aria-label="CHANNELS[id].label"
    >
        <path v-if="CHANNELS[id].fill" :d="CHANNELS[id].path" fill="currentColor" />
        <template v-else-if="CHANNELS[id].shape === 'instagram'">
            <rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="1.8" />
            <circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8" />
            <circle cx="17.5" cy="6.5" r="1" fill="currentColor" />
        </template>
        <template v-else-if="CHANNELS[id].shape === 'mail'">
            <rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.8" />
            <path d="m4 7 8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </template>
    </svg>
</template>
