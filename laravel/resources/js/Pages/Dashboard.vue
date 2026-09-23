<script setup>
import { computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import DashStatWidget from '@/Components/DashStatWidget.vue';
import DualAxisChart from '@/Components/DualAxisChart.vue';
import ChannelDonut from '@/Components/ChannelDonut.vue';
import PostsTable from '@/Components/PostsTable.vue';
import WidgetHeader from '@/Components/Layout/WidgetHeader.vue';
import TopChannelCard from '@/Components/Domain/TopChannelCard.vue';
import ChannelMetricsPanel from '@/Components/Domain/ChannelMetricsPanel.vue';

// Metriche vere da dati interni (App\Services\DashboardMetrics, fase 1):
// niente visualizzazioni/utenti unici finché non arrivano gli insights delle
// piattaforme (fase 2).
const props = defineProps({
    metrics: { type: Object, required: true },
    periodDays: { type: Number, required: true },
    recentPosts: { type: Array, required: true },
});

const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? 'Mario Rossi');

// isAdmin/isManager/activeUser sono condivisi globalmente (HandleInertiaRequests)
// e già usati da AppLayout per il filtro sidebar + barra di contesto; qui
// servono solo per adattare l'header/i sottotitoli di questa pagina.
const isAdmin = computed(() => usePage().props.isAdmin);
const isManager = computed(() => usePage().props.isManager);
const activeUser = computed(() => usePage().props.activeUser);

const s = computed(() => props.metrics.stats);
const period = computed(() => `ultimi ${props.periodDays} giorni`);

</script>

<template>
    <Head title="Dashboard" />

    <AppLayout current="dashboard" :user="userName">
        <div class="dash-head">
            <div v-if="activeUser" style="display: flex; align-items: center; gap: 16px">
                <div class="scope-av" style="width: 54px; height: 54px; font-size: 21.6px; background: var(--sky-50); color: var(--sky-strong)">
                    {{ activeUser.name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() }}
                </div>
                <div style="min-width: 0">
                    <h1 style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap">
                        {{ activeUser.name }}
                        <span v-if="activeUser.role === 'manager' || activeUser.role === 'amministratore'" class="scope-rb" :class="activeUser.role">
                            {{ activeUser.role === 'manager' ? 'Manager' : 'Admin' }}
                        </span>
                    </h1>
                    <p class="muted" style="margin: 6px 0 0">Dati utente · {{ activeUser.email }} · {{ period }}</p>
                </div>
            </div>
            <div v-else>
                <h1>Ciao, {{ userName.split(' ')[0] }} 👋</h1>
                <p class="muted">{{ isAdmin ? 'Riepilogo di tutti gli utenti' : isManager ? 'Riepilogo dei tuoi utenti' : 'Riepilogo' }} · {{ period }} · aggiornato adesso</p>
            </div>
        </div>

        <div class="stat-grid">
            <DashStatWidget :index="0" icon="calendar" label="Post pubblicati" :value="s.posts.value" sub="vs periodo precedente" :trend="s.posts.trend" color="var(--g500)" :spark-data="s.posts.spark" />
            <DashStatWidget :index="1" icon="send" label="Uscite sui canali" :value="s.outputs.value" sub="post × canale" :trend="s.outputs.trend" color="var(--sky)" :spark-data="s.outputs.spark" />
            <DashStatWidget :index="2" icon="chat" label="Commenti ricevuti" :value="s.comments.value" sub="interazioni sui social" :trend="s.comments.trend" color="var(--primary)" :spark-data="s.comments.spark" />
            <DashStatWidget :index="3" icon="sparkles" label="Risposte AI" :value="s.replies.value" sub="inviate in automatico" :trend="s.replies.trend" color="var(--sky-strong)" :spark-data="s.replies.spark" />
        </div>

        <div class="card card-pad dash-card">
            <WidgetHeader title="Andamento comunicazione" sub="Pubblicazioni e commenti — ultimi 12 mesi" />
            <DualAxisChart :data="metrics.monthly" />
        </div>

        <div class="dash-grid">
            <div class="card card-pad card-col">
                <WidgetHeader title="Distribuzione per canale" :sub="`% pubblicazioni per piattaforma · ${period}`" />
                <div class="donut-wrap">
                    <ChannelDonut :data="metrics.channels" />
                </div>
            </div>
            <div class="card card-pad card-col">
                <WidgetHeader title="Canale più attivo" :sub="`Pubblicazioni · ${period}`" />
                <TopChannelCard :data="metrics.channels" />
            </div>
        </div>

        <ChannelMetricsPanel :data="metrics.channels" />

        <div class="card card-clip">
            <div class="card-pad card-pad--flush">
                <WidgetHeader
                    title="Ultimi post inviati"
                    :sub="activeUser ? `I post più recenti di ${activeUser.name}` : 'I 10 post più recenti su tutti i canali'"
                >
                    <template #action>
                        <button class="btn btn-light btn-sm" @click="router.visit(route('posts'))">
                            Vedi tutti <Icon name="arrowRight" :size="15" />
                        </button>
                    </template>
                </WidgetHeader>
            </div>
            <PostsTable :posts="recentPosts" />
        </div>
    </AppLayout>
</template>
