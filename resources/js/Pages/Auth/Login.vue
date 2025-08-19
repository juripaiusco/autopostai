<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import {Head, Link, useForm, usePage} from '@inertiajs/vue3';
import {__} from "@/ComponentsExt/Translations.js";

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <div class="form-floating">
                    <TextInput
                        id="username"
                        type="email"
                        class="mt-1 block w-full form-control"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="name@example.com"
                    />
                    <InputLabel for="username" value="Nome utente" class="!text-base !text-gray-600" />
                </div>

                <!-- <InputError class="mt-2" :message="form.errors.email" /> -->
            </div>

            <div class="mt-4">
                <div class="form-floating">
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full form-control"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="password"
                    />
                    <InputLabel for="password" value="Password" class="!text-base !text-gray-600" />
                </div>

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 block">
                <label class="flex items-center">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="text-sm text-gray-600 dark:text-gray-400 mb-1"
                        >{{ __('login.remberme') }}</span
                    >
                </label>
            </div>

            <InputError class="mt-4 text-center"
                        :message="__(form.errors.email)" />

            <div class="mt-6 flex items-center justify-center">
                <!--<Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                >
                    Forgot your password?
                </Link>-->

                <PrimaryButton
                    class="btn btn-primary w-full !text-xl sm:!text-base"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ __('login.login') }}
                </PrimaryButton>
            </div>

            <br>

            <div class="lg:container lg:m-auto text-gray-300 dark:text-gray-700 text-sm text-center">
                <small>v.{{ usePage().props.app.version }}</small>
            </div>
        </form>
    </GuestLayout>
</template>
