<script setup>
import { ref, computed } from 'vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import ToggleSwitch from '@/Components/UI/ToggleSwitch.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { usesContent } from '@/lib/newsletterTemplate.js';

/**
 * Passo "Scrivi": HTML personalizzato per la newsletter di questo post, al
 * posto del template dell'account. null = template dell'account.
 */
const props = defineProps({
    modelValue: { type: String, default: null },
    title: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'update:title']);

const enabled = computed(() => props.modelValue !== null && props.modelValue !== undefined);
const withContent = computed(() => usesContent(props.modelValue));
const confirmOff = ref(false);

function toggle(on) {
    if (on) {
        emit('update:modelValue', '');
        return;
    }
    if ((props.modelValue ?? '').trim()) {
        confirmOff.value = true;
        return;
    }
    emit('update:modelValue', null);
}

function turnOff() {
    confirmOff.value = false;
    emit('update:modelValue', null);
}
</script>

<template>
    <div class="pf-comments-card">
        <label class="pf-comments-title" style="display:flex;align-items:center;gap:10px;cursor:pointer">
            <ToggleSwitch :model-value="enabled" @update:model-value="toggle" />
            <ChannelIcon id="newsletter" :size="14" />HTML personalizzato per questa email
        </label>
        <div class="pf-check-help">
            Incolla l'HTML della tua email: verrà inviato così com'è, al posto del modello impostato nell'account.
        </div>

        <template v-if="enabled">
            <ul class="pf-check-help pf-nl-codes">
                <li><code>[content]</code> — dove inserire il testo del post (scritto da te o dall'AI). Se non lo usi, l'email è solo il tuo HTML e l'AI non scrive nulla.</li>
                <li><code>[image 1]</code>, <code>[image 2]</code>… — le immagini del post, nell'ordine del passo Media.</li>
                <li><code>[cta url="https://…" text="Scopri di più"]</code> — pulsante con lo stile dell'account.</li>
                <li><code>[unsubscribe]</code> — link di disiscrizione; se manca viene aggiunto in fondo.</li>
            </ul>
            <label class="sr-only" for="nl-template-custom">HTML dell'email</label>
            <textarea id="nl-template-custom" class="control pf-nl-code" rows="12" spellcheck="false"
                :value="modelValue" placeholder="<html>&#10;  <body>&#10;    …&#10;  </body>&#10;</html>"
                @input="emit('update:modelValue', $event.target.value)" />

            <div v-if="!withContent" class="acc-field" style="margin:12px 0 0">
                <label class="acc-row-label" for="nl-subject">Oggetto dell'email</label>
                <span class="acc-row-help">Obbligatorio: senza <code>[content]</code> l'oggetto è il titolo del post.</span>
                <input id="nl-subject" class="control" type="text" :value="title"
                    @input="emit('update:title', $event.target.value)" />
            </div>
        </template>

        <ConfirmModal v-if="confirmOff" title="Tornare al modello dell'account?" confirm-label="Sì, elimina l'HTML" danger
            @confirm="turnOff" @cancel="confirmOff = false">
            L'HTML che hai incollato verrà eliminato e la newsletter userà il modello impostato nell'account.
        </ConfirmModal>
    </div>
</template>

<style scoped>
.pf-nl-codes {
    margin: 8px 0 10px;
    padding-left: 18px;
    list-style: disc;
}
.pf-nl-codes li { margin-bottom: 2px; }
</style>
