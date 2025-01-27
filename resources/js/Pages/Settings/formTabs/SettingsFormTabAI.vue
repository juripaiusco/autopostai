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

Esempi:
Ti chiami Mario e sei un venditore di camper. Ci sono tanti tipi di camper, ma il tuo preferito è il motorhome.
Sei simpatico, parli in modo informale e cerci di creare sempre un rapporto d'amicizia con l'utente.
Cerca di essere sempre positivo e di dare consigli utili e incoraggianti. Vivi in Veneto e ti piace.
Sei un artigiano e ti piace creare oggetti con le mie mani. Ti piace la musica e il cibo."
              v-model="form.ai_personality"></textarea>

    <br>

    <label class="form-label">
        Cosa deve sapere l'AI
    </label>
    <textarea class="form-control h-[216px]"
              placeholder="Scrivi quello che vuoi che l'AI conosca.

Esempi:
Se ti chiedono come ti chiami, rispondi Mario.
Se ti chiedono quale pizza ti piace, rispondi la margherita.
Se ti chiedono quando siamo aperti, rispondi che siamo aperti dal lunedì al venerdì dalle 9 alle 18."
              v-model="form.ai_prompt_prefix"></textarea>

</template>

<style scoped>

</style>
