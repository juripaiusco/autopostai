<script setup>
import ChannelIcon from '@/Components/ChannelIcon.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import Icon from '@/Components/Icon.vue';
import { CH_CONFIG } from '@/data/dashboardMock';

const props = defineProps({
    comment: { type: Object, required: true },
});

const def = CH_CONFIG[props.comment.channel] ?? { label: props.comment.channel, color: '#6b7280' };

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="card ps-comment">
        <div class="ps-comment__row">
            <div class="ps-comment__avatar-wrap">
                <UserAvatar :name="comment.author || '?'" :size="38" />
                <span class="ps-comment__ch-badge" :style="{ color: def.color }">
                    <ChannelIcon :id="comment.channel" :size="11" />
                </span>
            </div>
            <div class="ps-comment__body">
                <div class="ps-comment__meta">
                    <span class="ps-comment__author">{{ comment.author }}</span>
                    <span class="ps-comment__sep">·</span>
                    <span class="ps-comment__time">{{ formatDate(comment.time) }}</span>
                </div>
                <div class="ps-comment__text">{{ comment.text }}</div>
            </div>
        </div>

        <div v-if="comment.reply" class="ps-comment__reply">
            <div class="ps-comment__reply-head">
                <span class="ps-comment__reply-badge">
                    <Icon name="sparkles" :size="11" /> Risposta AI
                </span>
                <span class="ps-comment__reply-time">{{ formatDate(comment.reply.time) }}</span>
                <span class="ps-comment__reply-tokens" v-if="comment.reply.tokens">{{ comment.reply.tokens }} token</span>
            </div>
            <div class="ps-comment__reply-text">{{ comment.reply.text }}</div>
        </div>
    </div>
</template>
