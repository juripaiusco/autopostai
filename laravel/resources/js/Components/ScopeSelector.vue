<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    users: { type: Array, required: true },
    modelValue: { type: Number, default: null },
    variant: { type: String, default: 'sidebar' }, // 'sidebar' | 'topbar'
    align: { type: String, default: 'left' }, // 'left' | 'right'
    openUp: { type: Boolean, default: false },
    generalLabel: { type: String, default: 'Vista generale' },
    generalSub: { type: String, default: 'Tutti gli utenti' },
});

const emit = defineEmits(['update:modelValue']);

const ROLE_LABEL = { amministratore: 'Admin', manager: 'Manager', utente: 'Utente' };

function initials(name) {
    return name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase();
}
function hue(name) {
    return (name.charCodeAt(0) * 17) % 360;
}

const open = ref(false);
const q = ref('');
const kbd = ref(0);
const inputRef = ref(null);

const activeUser = computed(() => props.users.find((u) => u.id === props.modelValue) || null);

const filtered = computed(() => {
    const s = q.value.toLowerCase().trim();
    if (!s) return props.users;
    return props.users.filter((u) => u.name.toLowerCase().includes(s) || u.email.toLowerCase().includes(s));
});

const showGeneral = computed(() => !q.value.trim());
const navList = computed(() => (showGeneral.value ? [null, ...filtered.value] : filtered.value));

watch([q, open], () => { kbd.value = 0; });
watch(open, async (isOpen) => {
    if (isOpen) {
        await nextTick();
        inputRef.value?.focus();
    }
});

function select(id) {
    emit('update:modelValue', id);
    open.value = false;
    q.value = '';
}

function onKeydown(e) {
    if (e.key === 'Escape') { open.value = false; return; }
    if (e.key === 'ArrowDown') { e.preventDefault(); kbd.value = Math.min(kbd.value + 1, navList.value.length - 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); kbd.value = Math.max(kbd.value - 1, 0); }
    else if (e.key === 'Enter') {
        e.preventDefault();
        const pick = navList.value[kbd.value];
        select(pick == null ? null : pick.id);
    }
}
</script>

<template>
    <div class="scope-sel">
        <button
            type="button"
            class="scope-trigger"
            :class="[`scope-trigger--${variant}`, { active: activeUser, open }]"
            :title="activeUser ? `Filtro attivo: ${activeUser.name}` : 'Filtra per utente'"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="open = !open"
        >
            <div v-if="activeUser" class="scope-av" :style="{ width: '28px', height: '28px', fontSize: '11.2px', background: `oklch(0.82 0.11 ${hue(activeUser.name)})`, color: `oklch(0.30 0.09 ${hue(activeUser.name)})` }">
                {{ initials(activeUser.name) }}
            </div>
            <span v-else class="scope-glyph"><Icon name="users" :size="16" /></span>

            <span class="scope-tx">
                <span class="scope-tx-main">{{ activeUser ? activeUser.name : generalLabel }}</span>
                <span class="scope-tx-sub">{{ activeUser ? ROLE_LABEL[activeUser.role] : generalSub }}</span>
            </span>

            <span v-if="activeUser" class="scope-clear" title="Torna alla vista generale" @click.stop="select(null)">
                <Icon name="x" :size="15" />
            </span>
            <span v-else class="scope-caret"><Icon name="chevron" :size="15" /></span>
        </button>

        <template v-if="open">
            <div class="scope-overlay" @click="open = false"></div>
            <div class="scope-menu" :class="{ right: align === 'right', up: openUp }" role="listbox" @keydown="onKeydown">
                <div class="scope-search">
                    <Icon name="search" :size="16" />
                    <input ref="inputRef" v-model="q" placeholder="Cerca utente per nome o email…" @keydown="onKeydown" />
                    <button v-if="q" type="button" class="scope-search-x" @click="q = ''"><Icon name="x" :size="14" /></button>
                </div>
                <div class="scope-list">
                    <div
                        v-if="showGeneral"
                        class="scope-opt scope-general"
                        :class="{ sel: modelValue == null, kbd: kbd === 0 }"
                        role="option"
                        :aria-selected="modelValue == null"
                        @click="select(null)"
                        @mouseenter="kbd = 0"
                    >
                        <span class="scope-glyph"><Icon name="users" :size="16" /></span>
                        <div class="scope-opt-body">
                            <div class="scope-opt-name">{{ generalLabel }}</div>
                            <div class="scope-opt-sub">{{ generalSub }}</div>
                        </div>
                        <span v-if="modelValue == null" class="scope-opt-check"><Icon name="check" :size="17" /></span>
                    </div>

                    <div v-if="showGeneral && filtered.length > 0" class="scope-div"></div>
                    <div v-if="filtered.length === 0" class="scope-empty">Nessun utente trovato</div>

                    <div
                        v-for="(u, i) in filtered"
                        :key="u.id"
                        class="scope-opt"
                        :class="{ sel: modelValue === u.id, kbd: kbd === (showGeneral ? i + 1 : i) }"
                        role="option"
                        :aria-selected="modelValue === u.id"
                        @click="select(u.id)"
                        @mouseenter="kbd = showGeneral ? i + 1 : i"
                    >
                        <div class="scope-av" :style="{ width: '30px', height: '30px', fontSize: '12px', background: `oklch(0.82 0.11 ${hue(u.name)})`, color: `oklch(0.30 0.09 ${hue(u.name)})` }">
                            {{ initials(u.name) }}
                        </div>
                        <div class="scope-opt-body">
                            <div class="scope-opt-name">
                                {{ u.name }}
                                <span v-if="u.role === 'manager' || u.role === 'amministratore'" class="scope-rb" :class="u.role">{{ ROLE_LABEL[u.role] }}</span>
                            </div>
                            <div class="scope-opt-sub">{{ u.email }}</div>
                        </div>
                        <span v-if="modelValue === u.id" class="scope-opt-check"><Icon name="check" :size="17" /></span>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
