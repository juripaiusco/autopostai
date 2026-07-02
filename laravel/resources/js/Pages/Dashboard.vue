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
import { GLOBAL_METRICS, CHANNEL_METRICS, MONTHLY_DATA } from '@/data/dashboardMock';

defineProps({
    postsCount: { type: Number, required: true },
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

const g = GLOBAL_METRICS;

/* Sparkline seeds (7 settimane) */
const vSpark = [18, 22, 19, 28, 31, 36, 41];
const uSpark = [8, 10, 9, 13, 15, 17, 20];
const cSpark = [89, 95, 112, 141, 178, 267, 334];
const pSpark = [8, 11, 9, 14, 13, 16, 15];

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
                    <p class="muted" style="margin: 6px 0 0">Dati utente · {{ activeUser.email }} · ultimi 90 giorni</p>
                </div>
            </div>
            <div v-else>
                <h1>Ciao, {{ userName.split(' ')[0] }} 👋</h1>
                <p class="muted">{{ isAdmin ? 'Riepilogo di tutti gli utenti' : isManager ? 'Riepilogo dei tuoi utenti' : 'Riepilogo' }} · ultimi 90 giorni · aggiornato adesso</p>
            </div>
        </div>

        <div class="stat-grid">
            <DashStatWidget :index="0" icon="calendar" label="Post inviati" :value="postsCount" :sub="activeUser ? 'pubblicati dall\'utente' : 'su tutti i canali'" :trend="-4" color="var(--g500)" :spark-data="pSpark" />
            <DashStatWidget :index="1" icon="eye" label="Views totali" :value="g.views" sub="vs periodo precedente" :trend="14" color="var(--sky)" :spark-data="vSpark" />
            <DashStatWidget :index="2" icon="users" label="Utenti unici" :value="g.unique" sub="audience raggiunta" :trend="9" color="var(--sky-strong)" :spark-data="uSpark" />
            <DashStatWidget :index="3" icon="chat" label="Commenti" :value="g.comments" sub="interazioni ricevute" :trend="22" color="var(--primary)" :spark-data="cSpark" />
        </div>

        <div class="card card-pad dash-card">
            <WidgetHeader title="Andamento comunicazione" sub="Views e commenti — ultimi 12 mesi" />
            <DualAxisChart :data="MONTHLY_DATA" />
        </div>

        <div class="dash-grid">
            <div class="card card-pad card-col">
                <WidgetHeader title="Distribuzione per canale" sub="% views per piattaforma" />
                <div class="donut-wrap">
                    <ChannelDonut :data="CHANNEL_METRICS" />
                </div>
            </div>
            <div class="card card-pad card-col">
                <WidgetHeader title="Canale più attivo" sub="Views · ultimi 90 giorni" />
                <TopChannelCard :data="CHANNEL_METRICS" />
            </div>
        </div>

        <ChannelMetricsPanel :data="CHANNEL_METRICS" />

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
