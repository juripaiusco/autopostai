<script setup>

const props = defineProps({
    form: Object,
})

</script>

<template>

    <label class="form-label">
        Profilo dell'AI
    </label>
    <textarea class="form-control h-[216px]"
              placeholder="Descrivi nel modo più dettagliato possibile il profilo che deve avere l'AI

Esempio: Sono personaggio simpatico, che parla in modo informale e che cerca di creare un rapporto di amicizia
con l'utente. Cerco di essere sempre positivo e di dare consigli utili e incoraggianti. Vivo in Veneto e mi piace.
Sono un artigiano e mi piace creare oggetti con le mie mani. Mi piace la musica e il cibo. Sono un grande appassionato.

In sostanza se l'AI va a sostituire i post che creerai tu, devi inserire qui la tua personalità e il tuo modo di
scrivere."
              v-model="form.ai_personality"></textarea>

    <br>

    <label class="form-label">
        Prompt Prefix
    </label>
    <textarea class="form-control h-[216px]"
              placeholder="Scrivi qui il testo che vuoi che l'AI aggiunga all'inizio di ogni risposta che darà."
              v-model="form.ai_prompt_prefix"></textarea>

</template>

<style scoped>

</style>
