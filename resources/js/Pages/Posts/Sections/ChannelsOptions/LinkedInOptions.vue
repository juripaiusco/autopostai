<script setup>

import {computed} from "vue";

const props = defineProps({
    data: Object,
    form: Object,
    token: String
});

let form = props.form;

const selectedUserChannels = computed(() => {
    if (props.data.channels_user) {
        try {
            return JSON.parse(props.data.channels_user);
        } catch (e) {
            console.error('Errore nel parsing di channels_user:', e);
            return {};
        }
    } else {
        const user = props.data.users.find(user => user.id === props.form.user_id);
        if (!user) return {};

        try {
            return typeof user.channels === 'string'
                ? JSON.parse(user.channels)
                : user.channels || {};
        } catch (e) {
            console.error('Errore nel parsing dei canali dell\'utente:', e);
            return {};
        }
    }
});

</script>

<template>

    <div v-if="selectedUserChannels?.linkedin?.reply_on === '1'"
         class="card">
        <div class="card-header !text-gray-500">

            <span class="font-bold">{{ selectedUserChannels?.linkedin?.name }}</span> Opzioni

        </div>
        <div class="card-body">

            <div class="form-check form-switch !mb-3">

                <input class="form-check-input"
                       type="checkbox"
                       id="linkedin-comments"
                       true-value="1"
                       :false-value=null
                       v-model="form.channels['linkedin']['reply_on']"
                       checked />

                <label class="form-check-label"
                       for="linkedin-comments">
                    <span class="text-gray-500 text-[0.9em]">Abilita commenti</span>
                </label>

            </div>

        </div>
    </div>
</template>

<style scoped>

</style>
