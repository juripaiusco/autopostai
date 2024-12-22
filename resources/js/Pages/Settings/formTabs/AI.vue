<script setup>

const props = defineProps({
    form: Object,
})

</script>

<template>

    <label class="form-label">Descrivi chi deve impersonare l'AI</label>
    <textarea class="form-control h-[216px]"
              v-model="form.ia_thinking"></textarea>

</template>

<style scoped>

</style>
