<script setup>
import { computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import DashStatWidget from '@/Components/DashStatWidget.vue';
import DualAxisChart from '@/Components/DualAxisChart.vue';
import ChannelDonut from '@/Components/ChannelDonut.vue';
import ChannelMetricsTable from '@/Components/ChannelMetricsTable.vue';
import PostsTable from '@/Components/PostsTable.vue';
import WidgetHeader from '@/Components/Layout/WidgetHeader.vue';
import TopChannelCard from '@/Components/Domain/TopChannelCard.vue';
import { GLOBAL_METRICS, CHANNEL_METRICS, RECENT_POSTS, MONTHLY_DATA } from '@/data/dashboardMock';

const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? 'Mario Rossi');

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
            <div>
                <h1>Ciao, {{ userName.split(' ')[0] }} 👋</h1>
                <p class="muted">Riepilogo degli ultimi 90 giorni · aggiornato adesso</p>
            </div>
        </div>

        <div class="stat-grid">
            <DashStatWidget label="Views totali" :value="g.views" sub="vs periodo precedente" :trend="14" color="var(--sky)" :spark-data="vSpark" />
            <DashStatWidget label="Utenti unici" :value="g.unique" sub="audience raggiunta" :trend="9" color="var(--sky)" :spark-data="uSpark" />
            <DashStatWidget label="Commenti" :value="g.comments" sub="interazioni ricevute" :trend="22" color="var(--sky)" :spark-data="cSpark" />
            <DashStatWidget label="Post inviati" :value="g.posts" sub="su tutti i canali" :trend="-4" color="var(--g400)" :spark-data="pSpark" />
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

        <div class="card dash-card card-clip">
            <div class="card-pad card-pad--flush">
                <WidgetHeader title="Metriche per canale" sub="Views · utenti unici · commenti · ritorno medio — ultimi 90 giorni" />
            </div>
            <ChannelMetricsTable :data="CHANNEL_METRICS" />
        </div>

        <div class="card card-clip">
            <div class="card-pad card-pad--flush">
                <WidgetHeader title="Ultimi post inviati" sub="I 10 post più recenti su tutti i canali">
                    <template #action>
                        <button class="btn btn-light btn-sm" @click="router.visit(route('posts'))">
                            Vedi tutti <Icon name="arrowRight" :size="15" />
                        </button>
                    </template>
                </WidgetHeader>
            </div>
            <PostsTable :posts="RECENT_POSTS" />
        </div>
    </AppLayout>
</template>
