<script setup>
import { computed, ref } from 'vue';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';

// Select con ricerca (Headless UI Combobox) usato per scegliere un account.
// Si comporta come un <select>: la lista si apre cliccando ovunque sul campo,
// non solo sulla freccia, e scrivendo si filtra. Il v-model è l'id.
const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    // [{ id, ... }] — di default { id, name, email }
    options: { type: Array, required: true },
    id: { type: String, default: null },
    placeholder: { type: String, default: 'Seleziona…' },
    emptyText: { type: String, default: 'Nessun risultato' },
    optionLabel: { type: Function, default: (o) => (o ? `${o.name} - ${o.email}` : '') },
    // Campi su cui cerca il testo digitato.
    filterKeys: { type: Array, default: () => ['name', 'email'] },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const buttonRef = ref(null);

const selected = computed(() => props.options.find((o) => o.id === props.modelValue) ?? null);

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter((o) => props.filterKeys.some((k) => String(o[k] ?? '').toLowerCase().includes(q)));
});

function onPick(option) {
    query.value = '';
    emit('update:modelValue', option?.id ?? null);
}

// Headless UI v1 apre la lista solo da ComboboxButton (o col prop `immediate`,
// ma solo al primo focus): il click sull'input la apre passando dal bottone.
function openFromInput(open, event) {
    event.target.select();
    if (open) return;
    query.value = '';
    buttonRef.value?.$el.click();
}
</script>

<template>
    <Combobox :model-value="selected" :disabled="disabled" by="id" @update:model-value="onPick" v-slot="{ open }">
        <div class="cb-select">
            <ComboboxInput :id="id" class="control cb-select__input" autocomplete="off"
                :display-value="optionLabel" :placeholder="placeholder"
                @click="openFromInput(open, $event)"
                @change="query = $event.target.value" />
            <ComboboxButton ref="buttonRef" class="cb-select__btn" aria-label="Apri lista">
                <span aria-hidden="true">▾</span>
            </ComboboxButton>
            <ComboboxOptions class="cb-select__options">
                <div v-if="filtered.length === 0" class="cb-select__empty">{{ emptyText }}</div>
                <ComboboxOption v-for="o in filtered" :key="o.id" :value="o" v-slot="{ active, selected: isSelected }">
                    <div class="cb-select__option" :class="{ 'cb-select__option--active': active, 'cb-select__option--selected': isSelected }">
                        <slot name="option" :option="o" :active="active" :selected="isSelected">{{ optionLabel(o) }}</slot>
                    </div>
                </ComboboxOption>
            </ComboboxOptions>
        </div>
    </Combobox>
</template>

<style scoped>
.cb-select { position: relative; }
.cb-select__input { padding-right: 32px; cursor: pointer; }
.cb-select__input:focus { cursor: text; }
.cb-select__btn {
    position: absolute;
    top: 0;
    right: 0;
    height: 100%;
    width: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--g500);
    background: transparent;
    border: none;
    cursor: pointer;
}
.cb-select__options {
    position: absolute;
    z-index: 20;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 240px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid var(--g300);
    border-radius: var(--radius);
    box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
    padding: 4px;
}
.cb-select__empty { padding: 8px 10px; font-size: 13px; color: var(--g500); }
.cb-select__option { padding: 8px 10px; font-size: 13.5px; color: var(--g700); border-radius: 6px; cursor: pointer; }
.cb-select__option--active { background: var(--sky); color: #fff; }
.cb-select__option--selected { font-weight: 600; }
</style>
