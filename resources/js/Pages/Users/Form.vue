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
form.password = '';

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

                <div v-if="!$page.props.auth.user.parent_id || !$page.props.auth.user.child_on">

                    <h2 class="text-2xl font-bold">
                        Account Manager
                    </h2>

                    <small  class="text-xs font-normal">
                        Gestione amministrativa dell'account
                    </small>

                    <br><br>

                    <div class="card">
                        <div class="card-body">

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

                            <div class="row space-y-6 lg:space-y-0">
                                <div v-if="form.child_on === '0' || form.child_on === '' || form.child_on === null"
                                     class="col-lg">

                                    <label class="form-label">
                                        Scegli a quale manager assegnare questo account
                                        <br>
                                        <small>
                                            <span v-if="form.parent_id">
                                                L'account è assegnato a
                                                <span class="font-bold">
                                                    {{ form.managers.find(d => d.id === form.parent_id).name }}
                                                </span>
                                            </span>
                                            <span v-else>
                                                L'account non ha manager
                                            </span>
                                        </small>
                                    </label>
                                    <select class="form-select"
                                            aria-label="Default select example"
                                            v-model="form.parent_id">
                                        <option value="">Seleziona l'account</option>
                                        <option v-for="manager in data.managers" :key="manager.id" :value="manager.id">
                                            {{ manager.name }} - {{ manager.children.length }}/{{ manager.child_max }}
                                        </option>
                                    </select>

                                </div>
                                <div class="col-lg">

                                    <div v-if="form.child_on === '1' || form.child_on === 1">

                                        <label class="form-label">
                                            Numero account max
                                            <br>
                                            <small>Numero massimo di sotto account</small>
                                        </label>
                                        <input type="number"
                                               class="form-control"
                                               v-model="form.child_max" />
                                        <div class="text-red-500 text-center"
                                             v-if="form.errors.child_max">{{ __(form.errors.child_max) }}</div>

                                    </div>

                                    <div v-if="form.child_on === '0' || form.child_on === '' || form.child_on === null">

                                        <label class="form-label">
                                            Token al mese
                                            <br>
                                            <small>Numero massimo di token utilizzabili al mese</small>
                                        </label>
                                        <input type="number"
                                               class="form-control"
                                               v-model="form.tokens_limit" />
                                        <div class="text-red-500 text-center"
                                             v-if="form.errors.tokens_limit">{{ __(form.errors.tokens_limit) }}</div>

                                    </div>

                                </div>
                                <div class="col-lg">

                                    <label class="form-label">
                                        Immagini al giorno
                                        <br>
                                        <small>Numero massimo di immagini generabili al giorno</small>
                                    </label>
                                    <input type="number"
                                           class="form-control"
                                           v-model="form.image_model_limit" />
                                    <div class="text-red-500 text-center"
                                         v-if="form.errors.image_model_limit">{{ __(form.errors.image_model_limit) }}</div>

                                </div>
                            </div>

                        </div>
                    </div>

                    <br>

                </div>

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

                    </div>
                    <div class="col-lg">

                        <div v-if="form.child_on !== 1 && form.child_on !== '1'">

                            <h2 class="text-2xl font-bold">
                                Comunicazione Account
                            </h2>

                            <small  class="text-xs font-normal">
                                Scegli come gestire la comunicazione e in quali canali può pubblicare
                                {{ form.name ? form.name : 'questo account' }}
                            </small>

                            <br><br>

                            <label class="form-label">
                                Canali
                                <br>
                                <small>Scegli il canale e le varie opzioni</small>
                            </label>

                            <div v-for="(channel, index) in data.channels" :key="index"
                                 class="card [&:not(:last-child)]:mb-4">

                                <div class="card-body">

                                    <div class="form-check form-switch">

                                        <input class="form-check-input"
                                               type="checkbox"
                                               :id="index + '_channel_on'"
                                               true-value="1"
                                               false-value="0"
                                               v-model="form['channels'][index]['on']"
                                               checked />

                                        <label class="form-check-label"
                                               :for="index + '_channel_on'">
                                        <span class="capitalize text-gray-500 text-[0.9em]">
                                            {{ channel.name }}
                                        </span>
                                        </label>

                                    </div>

                                    <div v-if="form['channels'][index]['on'] === '1'"
                                         class="form-check form-switch">

                                        <input class="form-check-input"
                                               type="checkbox"
                                               :id="index + '_reply_on'"
                                               true-value="1"
                                               false-value="0"
                                               v-model="form['channels'][index]['reply_on']"
                                               checked />

                                        <label class="form-check-label"
                                               :for="index + '_reply_on'">
                                        <span class="text-gray-500 text-[0.9em]">
                                            Abilita risposte ai commenti
                                        </span>
                                        </label>

                                    </div>

                                    <div v-if="form['channels'][index]['on'] === '1' && form['channels'][index]['reply_on'] === '1'"
                                         class="mt-3">
                                        <label class="form-label !text-xs">
                                            Q.tà massima di risposte ai commenti per post (lasciando vuoto
                                            si risponde a tutti)
                                        </label>
                                        <input type="number"
                                               class="form-control form-control-sm"
                                               v-model="form['channels'][index]['reply_n']" />
                                    </div>

                                </div>

                            </div>

                        </div>

                        <br>

                    </div>
                </div>

                <div v-if="form.child_on !== 1 && form.child_on !== '1'">

                    <h2 class="text-2xl font-bold">Impostazioni Account</h2>

                    <small class="text-xs font-normal">
                        Le chiavi sono uniche, non duplicarle
                    </small>

                    <br><br>

                    <SettingsForm :form="!form.settings ? form : form.settings" />

                </div>

                <div class="text-right mt-10 flex flex-wrap justify-center md:justify-end">

                    <div class="w-1/2 text-center md:w-auto">
                        <Link class="btn btn-secondary w-[100%] md:w-[120px]"
                              :href="data.saveRedirect">
                            Annulla
                        </Link>
                    </div>

                    <div class="w-1/2 text-center md:w-auto">
                        <button type="submit"
                                class="btn btn-success ml-2 w-[100%] md:w-[120px]"
                                :disabled="form.processing">Salva</button>
                    </div>

                </div>

            </form>

        </ApplicationContainer>

    </AuthenticatedLayout>

</template>

<style scoped>

</style>
