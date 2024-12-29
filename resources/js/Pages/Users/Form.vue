<script setup>

import {Head, Link} from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ApplicationHeader from "@/Components/ApplicationHeader.vue";
import ApplicationContainer from "@/Components/ApplicationContainer.vue";
import {useForm} from "@inertiajs/vue3";
import SettingsForm from "@/Pages/Settings/SettingsForm.vue";

const props = defineProps({
    data: Object,
    filters: Object,
})

const dataForm = Object.fromEntries(Object.entries(props.data).map((v) => {
    return props.data ? v : '';
}));

const form = useForm(dataForm);

</script>

<template>

    <Head title="Account" />

    <AuthenticatedLayout>

        <template #header>

            <ApplicationHeader :breadcrumb-array="[
                'Account',
                data.id ?
                    form.name :
                        form.name.length > 0 ? form.name : 'Nuovo Account'
            ]" />

        </template>

        <ApplicationContainer>

            <div v-if="form.errors.message"
                 class="alert alert-danger">

                {{ form.errors.message }}

            </div>

            <form @submit.prevent="form.post(route(
                form.id ? 'user.update' : 'user.store',
                form.id ? form.id : ''
                ))">

                <div class="row">
                    <div class="col-lg">

                        <h2 class="text-2xl font-bold">
                            Profilo Account
                        </h2>

                        <small  class="text-xs font-normal">
                            <span v-if="form.parent">
                                Creato da {{ form.parent.name }} - {{ form.parent.email }}
                            </span>
                            <span v-else>
                                Sta venendo creato da {{ $page.props.auth.user.name }} - {{ $page.props.auth.user.email }}
                            </span>
                        </small>

                        <br><br>

                        <label class="form-label">
                            Nome
                            <br>
                            <small>Solo ad uso interno, non viene pubblicato</small>
                        </label>
                        <input type="text"
                               class="form-control"
                               v-model="form.name" />
                        <div class="text-red-500 text-center"
                             v-if="form.errors.name">{{ __(form.errors.name) }}</div>

                        <br>

                        <label class="form-label">
                            E-mail
                            <br>
                            <small>Solo ad uso interno, non viene pubblicato</small>
                        </label>
                        <input type="text"
                               class="form-control"
                               v-model="form.email" />
                        <div class="text-red-500 text-center"
                             v-if="form.errors.email">{{ __(form.errors.email) }}</div>

                        <br>

                        <label class="form-label">
                            Password
                            <br>
                            <small>Scegli una password sicura</small>
                        </label>
                        <input type="password"
                               class="form-control"
                               v-model="form.password" />
                        <div class="text-red-500 text-center"
                             v-if="form.errors.password">{{ __(form.errors.password) }}</div>

                        <br>

                        <div class="form-check form-switch !mb-3">

                            <input class="form-check-input"
                                   type="checkbox"
                                   id="child_on"
                                   true-value="1"
                                   false-value="0"
                                   v-model="form.child_on"
                                   checked />

                            <label class="form-check-label"
                                   for="child_on">
                                <span class="text-gray-500 text-[0.9em]">
                                    L'account può creare sotto utenti
                                    <br>
                                    <small>
                                        Nel caso in cui questo account abbia più brand da gestire
                                    </small>
                                </span>
                            </label>

                        </div>

                        <br>

                        <label class="form-label">
                            Numero account max
                            <br>
                            <small>Numero massimo di sotto account che possono essere generati</small>
                        </label>
                        <input type="text"
                               class="form-control"
                               v-model="form.child_max" />
                        <div class="text-red-500 text-center"
                             v-if="form.errors.child_max">{{ __(form.errors.child_max) }}</div>

                        <br>

                    </div>
                    <div class="col-lg">

                        <h2 class="text-2xl font-bold">Impostazioni Account</h2>

                        <small class="text-xs font-normal">
                            Le chiavi sono uniche, non duplicarle
                        </small>

                        <br><br>

                        <SettingsForm :form="!form.settings ? form : form.settings" />

                    </div>
                </div>

                <div class="text-right mt-10">

                    <Link class="btn btn-secondary w-[120px]"
                          :href="data.saveRedirect">
                        Annulla
                    </Link>

                    <button type="submit"
                            class="btn btn-success ml-2 w-[120px]">Salva</button>

                </div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>

</style>
