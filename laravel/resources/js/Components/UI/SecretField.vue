<script setup>
import { ref } from 'vue';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    id: { type: String, default: null },
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    readOnly: { type: Boolean, default: false },
    copyable: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const show = ref(false);
const copied = ref(false);

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
            :placeholder="placeholder"
            :readonly="readOnly"
            @input="emit('update:modelValue', $event.target.value)"
        />
        <button type="button" class="acc-iconbtn" :title="show ? 'Nascondi' : 'Mostra'" :aria-label="show ? 'Nascondi valore' : 'Mostra valore'" @click="show = !show">
            <Icon :name="show ? 'eyeOff' : 'eye'" :size="17" />
        </button>
        <button v-if="copyable" type="button" class="acc-iconbtn" title="Copia" aria-label="Copia valore" @click="copy">
            <Icon :name="copied ? 'check' : 'copy'" :size="17" />
        </button>
    </div>
</template>
