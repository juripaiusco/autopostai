<script setup>
import { computed, ref } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    id: { type: String, default: null },
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    readOnly: { type: Boolean, default: false },
    copyable: { type: Boolean, default: false },
    // Segreto già salvato: il server non lo rimanda mai in chiaro, solo un
    // hint ("••••1234"). Campo vuoto = resta quello salvato.
    savedHint: { type: String, default: null },
    // Rimozione richiesta per il prossimo salvataggio (emit 'clear').
    cleared: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'clear']);

const show = ref(false);
const copied = ref(false);

const effectivePlaceholder = computed(() => {
    if (props.cleared) return 'Verrà rimossa al salvataggio';
    if (props.savedHint) {
        return props.readOnly ? props.savedHint : `Salvata ${props.savedHint} · lascia vuoto per non cambiarla`;
    }
    return props.placeholder;
});

function copy() {
    try { navigator.clipboard?.writeText(props.modelValue || ''); } catch {}
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 1400);
}
</script>

<template>
    <div class="acc-secret">
        <input
            :id="id"
            class="control"
            :type="show ? 'text' : 'password'"
            :value="modelValue"
            :placeholder="effectivePlaceholder"
            :readonly="readOnly"
            :disabled="cleared"
            autocomplete="new-password"
            @input="emit('update:modelValue', $event.target.value)"
        />
        <button type="button" class="acc-iconbtn" :title="show ? 'Nascondi' : 'Mostra'" :aria-label="show ? 'Nascondi valore' : 'Mostra valore'" @click="show = !show">
            <Icon :name="show ? 'eyeOff' : 'eye'" :size="17" />
        </button>
        <button v-if="copyable" type="button" class="acc-iconbtn" title="Copia" aria-label="Copia valore" @click="copy">
            <Icon :name="copied ? 'check' : 'copy'" :size="17" />
        </button>
        <button v-if="savedHint && !readOnly" type="button" class="acc-iconbtn"
            :title="cleared ? 'Annulla rimozione' : 'Rimuovi il valore salvato'"
            :aria-label="cleared ? 'Annulla rimozione' : 'Rimuovi il valore salvato'"
            @click="emit('update:modelValue', ''); emit('clear', !cleared)">
            <Icon :name="cleared ? 'x' : 'trash'" :size="17" />
        </button>
    </div>
</template>
