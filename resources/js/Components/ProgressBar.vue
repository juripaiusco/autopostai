<script setup>

const props = defineProps({
    percent: Number,
});

let classNameBarContainer = '!bg-green-100'
let classNameBar = '!bg-green-500'

if (props.percent >= 75) {
    classNameBarContainer = '!bg-yellow-100'
    classNameBar = '!bg-yellow-500'
}

if (props.percent >= 90) {
    classNameBarContainer = '!bg-red-100'
    classNameBar = '!bg-red-500'
}

</script>

<template>

    <div class="progress w-[100%] !h-[4px]"
         :class="classNameBarContainer">
        <div class="progress-bar"
             :class="classNameBar"
             :style="'width: ' + percent + '%'"></div>
    </div>

</template>

<style scoped>

</style>
