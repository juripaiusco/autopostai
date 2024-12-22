<script setup>

import Breadcrumb from "./Breadrumb.vue";

defineProps({
    breadcrumbArray: Array,
    textRight: String
})

</script>

<template>

    <div class="inline-flex w-full dark:text-white">
        <div class="w-full">

            <Breadcrumb :breadcrumb-array="breadcrumbArray" />

        </div>
        <!-- <div class="text-right w-1/2">

            <slot name="textRight" />

            {{ textRight }}

        </div> -->
    </div>
</template>
