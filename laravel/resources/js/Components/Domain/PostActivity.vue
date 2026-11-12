<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';

// Cella "Attività" della lista post: commenti social + invio newsletter.
// newsletter = Post::newsletterProgress() (null se il canale non è uscito).
const props = defineProps({
    comments: { type: Number, default: 0 },
    channels: { type: Array, default: () => [] },
    newsletter: { type: Object, default: null },
});

const hasSocial = computed(() => props.channels.some((c) => c !== 'newsletter'));
const nl = computed(() => props.newsletter);

const pct = computed(() => {
    if (!nl.value?.total) return 0;
    return Math.min(100, Math.round((nl.value.sent / nl.value.total) * 100));
});

const plural = (n, one, many) => `${n} ${n === 1 ? one : many}`;

const nlTitle = computed(() => {
    const n = nl.value;
    if (!n) return '';
    if (n.campaign) return 'Campagna newsletter inviata';

    const head = n.sending
        ? (n.total !== null ? `Invio in corso: ${n.sent} di ${n.total} email` : `Invio in corso: ${plural(n.sent, 'email inviata', 'email inviate')}`)
        : plural(n.sent, 'email inviata', 'email inviate');
    const parts = [head, plural(n.opened, 'aperta', 'aperte')];
    if (n.bounced) parts.push(plural(n.bounced, 'rimbalzata', 'rimbalzate'));
    return parts.join(' · ');
});
</script>

<template>
    <div v-if="hasSocial || nl" class="activity">
        <span v-if="hasSocial" class="activity__row" :title="plural(comments, 'commento', 'commenti')">
            <Icon name="chat" :size="13" class="activity__icon" />
            <span class="activity__val">{{ comments }}</span>
        </span>

        <span v-if="nl" class="activity__row" :title="nlTitle">
            <ChannelIcon id="newsletter" :size="13" class="activity__icon" />
            <span v-if="nl.campaign" class="activity__muted">Inviata</span>
            <template v-else>
                <span class="activity__val">
                    {{ nl.sent }}<span v-if="nl.total !== null" class="activity__muted">/{{ nl.total }}</span>
                </span>
                <span v-if="nl.opened" class="activity__opened">
                    <Icon name="eye" :size="12" />{{ nl.opened }}
                </span>
            </template>
        </span>

        <span v-if="nl?.sending && nl.total" class="activity__bar" role="progressbar"
              :aria-valuenow="pct" aria-valuemin="0" aria-valuemax="100" :aria-label="nlTitle">
            <span class="activity__fill" :style="{ transform: `scaleX(${pct / 100})` }"></span>
        </span>
    </div>
    <span v-else class="activity__muted">—</span>
</template>

<style scoped>
.activity{ display: inline-flex; flex-direction: column; align-items: flex-start; gap: 3px; font-variant-numeric: tabular-nums; font-size: 13px; line-height: 1.2; }
.activity__row{ display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
.activity__icon{ color: var(--g400); flex-shrink: 0; }
.activity__val{ color: var(--fg1); font-weight: 600; }
.activity__muted{ color: var(--fg3); font-weight: 500; }
.activity__opened{ display: inline-flex; align-items: center; gap: 2px; color: var(--fg2); font-size: 12px; margin-left: 2px; }
.activity__bar{ display: block; width: 100%; min-width: 56px; height: 3px; border-radius: 2px; background: var(--g200); overflow: hidden; }
.activity__fill{ display: block; width: 100%; height: 100%; background: var(--st-sch-fg); transform-origin: left center; }

@media (max-width: 640px){
    .activity{ align-items: flex-end; }
}
</style>
