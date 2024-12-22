<script setup lang="ts">

import Modal from "@/Components/Modal.vue";
import {Link} from "@inertiajs/vue3";

const props = defineProps({
    data: Object,
    url_confirm: String,
    show: Boolean
});

</script>

<template>

    <Modal :show="show">

        <div class="p-8 dark:text-white">

            <div class="inline-flex w-full">

                <div class="w-1/2 font-semibold">

                    <slot name="title" />

                </div>

                <div class="w-1/2 text-right">

                    <button @click="$emit('close')">

                        <svg class="w-5 h-5"
                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>

                    </button>

                </div>

            </div>

            <div class="mt-6 mb-10 text-center">

                <slot name="body" />

            </div>

            <div class="inline-flex w-full">

                <div class="w-1/2 mr-2">

                    <div class="btn btn-secondary w-full"
                         @click="$emit('close')">Annulla</div>

                </div>

                <div class="w-1/2 ml-2">

                    <Link v-if="data!.confirmURL"
                          class="btn w-full"
                          :class="data!.confirmBtnClass"
                          :href="data!.confirmURL">
                        {{ data!.confirmBtnText }}
                    </Link>

                    <button v-if="data!.confirmFNC === true"
                            class="btn w-full"
                            :class="data!.confirmBtnClass"
                            @click="$emit('fncConfirm', data)">
                        {{ data!.confirmBtnText }}
                    </button>

                </div>

            </div>

        </div>

    </Modal>

</template>
