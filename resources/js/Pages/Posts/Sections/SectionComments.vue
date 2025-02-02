<script setup>

import {__} from "@/ComponentsExt/Translations.js";

const props = defineProps({
    data: Object,
    form: Object,
});

</script>

<template>
    <label class="form-label">
        Prompt commenti
        <br>
        <small>L'AI risponde ai commenti in base alle tue indicazioni</small>
    </label>
    <textarea class="form-control h-[216px]"
              :class="{'!border !border-red-500' : form.errors.ai_prompt_comment}"
              v-model="form.ai_prompt_comment"
              placeholder="Esempio:
Se ti chiedono che tipo di pizza ti piace, rispondi margherita, ma senza mozzarella, con aggiunta di acciughe"></textarea>
    <div class="text-red-500 text-center text-xs"
         v-if="form.errors.ai_prompt_comment">{{ __(form.errors.ai_prompt_comment) }}</div>
</template>

<style scoped>

</style>
