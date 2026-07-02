<script setup>
import FieldRow from '@/Components/UI/FieldRow.vue';

defineProps({
    modelValue: { type: Object, required: true }, // { profile, knows, commentStyle }
});

const emit = defineEmits(['update']);
</script>

<template>
    <div style="display:flex;flex-direction:column;gap:4px">
        <FieldRow id="acc-ai-profile" label="Profilo dell'AI" help="Descrivi chi è l'AI: nome, ruolo, personalità e tono. È il prompt di sistema.">
            <textarea id="acc-ai-profile" class="control" :value="modelValue.profile" style="min-height:150px"
                placeholder="Descrivi nel modo più dettagliato possibile il profilo che deve avere l'AI…"
                @input="emit('update', 'profile', $event.target.value)" />
        </FieldRow>
        <FieldRow id="acc-ai-knows" label="Cosa deve sapere l'AI" help="Le informazioni concrete da conoscere: orari, prodotti, regole.">
            <textarea id="acc-ai-knows" class="control" :value="modelValue.knows" style="min-height:130px"
                placeholder="Scrivi quello che vuoi che l'AI conosca…"
                @input="emit('update', 'knows', $event.target.value)" />
        </FieldRow>
        <FieldRow id="acc-ai-comment" label="Come deve commentare l'AI" style="margin-bottom:0"
            help="Lo stile generale con cui l'AI risponde ai commenti e alle email.">
            <textarea id="acc-ai-comment" class="control" :value="modelValue.commentStyle" style="min-height:110px"
                placeholder="Scrivi come l'AI deve commentare e rispondere…"
                @input="emit('update', 'commentStyle', $event.target.value)" />
        </FieldRow>
    </div>
</template>
