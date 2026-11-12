<script setup>
import { computed } from 'vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';

// Cella "Attività" (lista post, post recenti Dashboard): commenti social +
// invio newsletter, una metrica per riga così restano leggibili anche con
// numeri grandi in una colonna stretta. newsletter = Post::newsletterProgress().
const props = defineProps({
    comments: { type: Number, default: 0 },
    channels: { type: Array, default: () => [] },
    newsletter: { type: Object, default: null },
});

const hasSocial = computed(() => props.channels.some((c) => c !== 'newsletter'));
const nl = computed(() => props.newsletter);
const smtp = computed(() => (nl.value && !nl.value.campaign ? nl.value : null));

// Il locale it non abbrevia le migliaia: 4.448 per intero, da 10.000 in su 12,4k / 1,2M.
// Il tooltip mantiene i numeri esatti.
function fmt(n) {
    const short = (v, unit) => `${v.toLocaleString('it-IT', { maximumFractionDigits: 1 })}${unit}`;
    if (n >= 1_000_000) return short(Math.floor(n / 100_000) / 10, 'M');
    if (n >= 10_000) return short(Math.floor(n / 100) / 10, 'k');
    return n.toLocaleString('it-IT');
}

const delivered = computed(() => (smtp.value ? Math.max(0, smtp.value.sent - smtp.value.bounced) : 0));
const openRate = computed(() => (delivered.value ? Math.round((smtp.value.opened / delivered.value) * 100) : 0));

const plural = (n, one, many) => `${n.toLocaleString('it-IT')} ${n === 1 ? one : many}`;

const nlTitle = computed(() => {
    const n = nl.value;
    if (!n) return '';
    if (n.campaign) return 'Campagna newsletter inviata';

    const head = n.sending
        ? (n.total !== null
            ? `Invio in corso: ${n.sent.toLocaleString('it-IT')} di ${n.total.toLocaleString('it-IT')} email`
            : `Invio in corso: ${plural(n.sent, 'email inviata', 'email inviate')}`)
        : plural(n.sent, 'email inviata', 'email inviate');
    const parts = [head, `${plural(n.opened, 'aperta', 'aperte')} (${openRate.value}%)`];
    if (n.bounced) parts.push(plural(n.bounced, 'rimbalzata', 'rimbalzate'));
    return parts.join(' · ');
});
</script>

<template>
    <div v-if="hasSocial || nl" class="activity" :title="nlTitle || null">
        <span v-if="hasSocial" class="activity__row" :title="plural(comments, 'commento', 'commenti')">
            <Icon name="chat" :size="13" class="activity__icon" />
            <span class="activity__val">{{ fmt(comments) }}</span>
        </span>

        <template v-if="nl">
            <span v-if="nl.campaign" class="activity__row">
                <ChannelIcon id="newsletter" :size="13" class="activity__icon" />
                <span class="activity__muted">Inviata</span>
            </span>

            <template v-else>
                <span class="activity__row">
                    <ChannelIcon id="newsletter" :size="13" class="activity__icon" />
                    <span class="activity__val">{{ fmt(nl.sent) }}</span>
                    <span v-if="nl.total !== null" class="activity__muted">/ {{ fmt(nl.total) }}</span>
                </span>

                <span v-if="delivered > 0" class="activity__row">
                    <Icon name="eye" :size="13" class="activity__icon" />
                    <span class="activity__val">{{ fmt(nl.opened) }}</span>
                    <span class="activity__muted">aperte · {{ openRate }}%</span>
                </span>
            </template>
        </template>
    </div>
    <span v-else class="activity__muted">—</span>
</template>

<style scoped>
.activity{ display: inline-flex; flex-direction: column; align-items: flex-start; gap: 4px; width: 100%; min-width: 0; max-width: 150px; font-variant-numeric: tabular-nums; font-size: 13px; line-height: 1.2; }
.activity__row{ display: flex; align-items: center; gap: 5px; max-width: 100%; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.activity__icon{ color: var(--g400); flex-shrink: 0; }
.activity__val{ color: var(--fg1); font-weight: 600; }
.activity__muted{ color: var(--fg3); font-weight: 500; font-size: 12px; overflow: hidden; text-overflow: ellipsis; }

@media (max-width: 640px){
    .activity{ align-items: flex-end; margin-left: auto; }
}
</style>
