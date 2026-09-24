<script setup>
import { computed, ref } from 'vue';
import Icon from '@/Components/Icon.vue';
import SearchInput from '@/Components/UI/SearchInput.vue';

// Scelta tra elementi remoti (liste newsletter, categorie WordPress, tag)
// resa come chip affiancati invece di righe impilate: con decine di voci la
// lista resta compatta. Singola scelta (id) o multipla (array di id); gli id
// sono confrontati come stringhe (dal provider arrivano numeri o stringhe).
const props = defineProps({
    // [{ id, name, count? }]
    options: { type: Array, required: true },
    modelValue: { type: [String, Number, Array], default: null },
    multiple: { type: Boolean, default: false },
    // Unità del conteggio nel tooltip del badge, es. "iscritti".
    countLabel: { type: String, default: '' },
    // Chip iniziale che vale null (solo singola scelta), es. { name: 'Tutti i contatti attivi', count: 120 }.
    noneOption: { type: Object, default: null },
    // Oltre questa soglia compare il campo di ricerca.
    searchThreshold: { type: Number, default: 12 },
    ariaLabel: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const selectedIds = computed(() => {
    if (props.multiple) return (props.modelValue ?? []).map(String);
    return props.modelValue === null || props.modelValue === undefined || props.modelValue === '' ? [] : [String(props.modelValue)];
});

function isSelected(option) {
    return selectedIds.value.includes(String(option.id));
}

function pick(option) {
    if (!props.multiple) {
        emit('update:modelValue', option.id);
        return;
    }
    const id = String(option.id);
    const next = isSelected(option)
        ? props.modelValue.filter((v) => String(v) !== id)
        : [...(props.modelValue ?? []), option.id];
    emit('update:modelValue', next);
}

const query = ref('');
const showSearch = computed(() => props.options.length > props.searchThreshold);
// Le voci selezionate restano sempre visibili, anche se non corrispondono alla ricerca.
const visible = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((o) => isSelected(o) || String(o.name).toLowerCase().includes(q));
});

function formatCount(n) {
    return Number(n).toLocaleString('it-IT');
}
</script>

<template>
    <div class="chip-picker">
        <SearchInput v-if="showSearch" v-model="query" class="chip-picker__search"
            :placeholder="`Cerca tra ${options.length}…`" @clear="query = ''" />

        <div class="chip-picker__list" role="group" :aria-label="ariaLabel">
            <button v-if="noneOption && !multiple" type="button" class="chip"
                :class="{ 'chip--active': selectedIds.length === 0 }" :aria-pressed="selectedIds.length === 0"
                @click="emit('update:modelValue', null)">
                <Icon v-if="selectedIds.length === 0" name="check" :size="13" />
                <span class="chip__name">{{ noneOption.name }}</span>
                <span v-if="noneOption.count != null" class="chip__count" :title="`${noneOption.count} ${countLabel}`">{{ formatCount(noneOption.count) }}</span>
            </button>

            <button v-for="o in visible" :key="o.id" type="button" class="chip"
                :class="{ 'chip--active': isSelected(o) }" :aria-pressed="isSelected(o)"
                :title="o.name" @click="pick(o)">
                <Icon v-if="isSelected(o)" name="check" :size="13" />
                <span class="chip__name">{{ o.name }}</span>
                <span v-if="o.count != null" class="chip__count" :title="`${o.count} ${countLabel}`">{{ formatCount(o.count) }}</span>
            </button>

            <span v-if="visible.length === 0" class="chip-picker__empty">Nessun risultato per "{{ query }}".</span>
        </div>
    </div>
</template>

<style scoped>
.chip-picker__search { margin-bottom: 10px; max-width: 320px; }
.chip-picker__list { display: flex; flex-wrap: wrap; gap: 6px; }
.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
    padding: 5px 10px;
    border: 1px solid var(--g200);
    border-radius: 999px;
    background: #fff;
    font-family: var(--font-ui);
    font-size: 13px;
    line-height: 1.3;
    color: var(--g700);
    cursor: pointer;
    transition: background .12s, border-color .12s, color .12s;
}
.chip:hover { background: var(--sky-50); border-color: var(--sky-200); }
.chip:focus-visible { outline: 2px solid var(--sky); outline-offset: 2px; }
.chip--active { background: var(--sky-50); border-color: var(--sky); color: var(--sky-strong); font-weight: 600; }
.chip svg { flex-shrink: 0; color: var(--sky-strong); }
.chip__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 260px; }
.chip__count {
    flex-shrink: 0;
    font-size: 11.5px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: var(--g500);
    background: var(--g100);
    border-radius: 999px;
    padding: 0 7px;
}
.chip--active .chip__count { background: var(--sky-200); color: var(--sky-strong); }
.chip-picker__empty { font-size: 13px; color: var(--g500); padding: 4px 0; }
</style>
