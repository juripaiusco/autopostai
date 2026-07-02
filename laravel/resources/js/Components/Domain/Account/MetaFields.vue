<script setup>
import FieldRow from '@/Components/UI/FieldRow.vue';
import ConnectionBadge from '@/Components/UI/ConnectionBadge.vue';

defineProps({
    modelValue: { type: Object, required: true }, // { pageId, connected }
});

const emit = defineEmits(['update']);
</script>

<template>
    <div>
        <FieldRow id="acc-meta-pageid" label="ID della pagina Facebook"
            help="L'ID numerico della pagina su cui pubblicare. Instagram pubblica tramite la pagina collegata.">
            <input id="acc-meta-pageid" class="control" type="text" :value="modelValue.pageId" placeholder="es. 104882736591023"
                @input="emit('update', 'pageId', $event.target.value)" />
        </FieldRow>
        <div class="acc-verify-row">
            <ConnectionBadge :state="modelValue.connected ? 'ok' : 'off'" />
            <span class="acc-hint-inline">Le chiavi sono uniche, non duplicarle.</span>
        </div>
    </div>
</template>
