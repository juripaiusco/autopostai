<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import DashStatWidget from '@/Components/DashStatWidget.vue';
import MiniSparkline from '@/Components/MiniSparkline.vue';
import DualAxisChart from '@/Components/DualAxisChart.vue';
import ChannelDonut from '@/Components/ChannelDonut.vue';
import ChannelMetricsTable from '@/Components/ChannelMetricsTable.vue';
import PostsTable from '@/Components/PostsTable.vue';
import { GLOBAL_METRICS, CHANNEL_METRICS, RECENT_POSTS, MONTHLY_DATA } from '@/data/dashboardMock';

const user = computed(() => usePage().props.auth?.user);
const userName = computed(() => user.value?.name ?? 'Mario Rossi');

const g = GLOBAL_METRICS;

/* Sparkline seeds (7 settimane) */
const vSpark = [18, 22, 19, 28, 31, 36, 41];
const uSpark = [8, 10, 9, 13, 15, 17, 20];
const cSpark = [89, 95, 112, 141, 178, 267, 334];
const pSpark = [8, 11, 9, 14, 13, 16, 15];

const avgViewsPerPost = computed(() => (g.posts > 0 ? Math.round(g.views / g.posts) : 0));
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout current="dashboard" :user="userName">
        <div class="dash-head">
            <div>
                <h1 style="margin: 0; font-size: 40px; font-weight: 600; line-height: 1.4">Ciao, {{ userName.split(' ')[0] }} 👋</h1>
                <p class="muted" style="margin: 5px 0 0; font-size: 14px">
                    Riepilogo degli ultimi 90 giorni · aggiornato adesso
                </p>
            </div>
        </div>

        <div class="stat-grid">
            <DashStatWidget label="Views totali" :value="g.views" sub="vs periodo precedente" :trend="14" color="var(--sky)" :spark-data="vSpark" />
            <DashStatWidget label="Utenti unici" :value="g.unique" sub="audience raggiunta" :trend="9" color="var(--sky)" :spark-data="uSpark" />
            <DashStatWidget label="Commenti" :value="g.comments" sub="interazioni ricevute" :trend="22" color="var(--sky)" :spark-data="cSpark" />
            <DashStatWidget label="Post inviati" :value="g.posts" sub="su tutti i canali" :trend="-4" color="var(--g400)" :spark-data="pSpark" />
        </div>

        <div class="card card-pad" style="margin-bottom: 20px">
            <div class="widget-head">
                <div>
                    <div class="widget-title">Andamento comunicazione</div>
                    <div class="widget-sub muted">Views e commenti — ultimi 12 mesi</div>
                </div>
            </div>
            <DualAxisChart :data="MONTHLY_DATA" />
        </div>

        <div class="dash-grid" style="margin-bottom: 20px">
            <div class="card card-pad" style="display: flex; flex-direction: column">
                <div class="widget-head">
                    <div class="widget-title">Distribuzione per canale</div>
                    <div class="widget-sub muted">% views per piattaforma</div>
                </div>
                <div style="flex: 1; display: flex; align-items: center">
                    <ChannelDonut :data="CHANNEL_METRICS" />
                </div>
            </div>
            <div class="card card-pad">
                <div class="widget-head">
                    <div>
                        <div class="widget-title">Post e views generate</div>
                        <div class="widget-sub muted">Ultimi 90 giorni</div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 14px">
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; background: var(--g50); border-radius: 10px">
                        <div>
                            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--g500); margin-bottom: 6px">Post creati</div>
                            <div style="font-size: 34px; font-weight: 800; color: var(--ink); line-height: 1">{{ g.posts }}</div>
                            <div style="font-size: 12px; color: var(--g500); margin-top: 5px">su tutti i canali</div>
                        </div>
                        <MiniSparkline :data="pSpark" color="var(--g400)" />
                    </div>
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 16px 18px; background: var(--sky-50); border-radius: 10px">
                        <div>
                            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: var(--sky-strong); margin-bottom: 6px">Views generate</div>
                            <div style="font-size: 34px; font-weight: 800; color: var(--ink); line-height: 1">{{ g.views.toLocaleString('it-IT') }}</div>
                            <div style="font-size: 12px; color: var(--g500); margin-top: 5px">media {{ avgViewsPerPost.toLocaleString('it-IT') }} views/post</div>
                        </div>
                        <MiniSparkline :data="vSpark" color="var(--sky)" />
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 20px; overflow: hidden">
            <div class="card-pad" style="padding-bottom: 0">
                <div class="widget-head">
                    <div>
                        <div class="widget-title">Metriche per canale</div>
                        <div class="widget-sub muted">Views · utenti unici · commenti · ritorno medio — ultimi 90 giorni</div>
                    </div>
                </div>
            </div>
            <ChannelMetricsTable :data="CHANNEL_METRICS" />
        </div>

        <div class="card" style="overflow: hidden">
            <div class="card-pad" style="padding-bottom: 0">
                <div class="widget-head">
                    <div>
                        <div class="widget-title">Ultimi post inviati</div>
                        <div class="widget-sub muted">I 10 post più recenti su tutti i canali</div>
                    </div>
                    <button class="btn btn-light btn-sm" @click="() => {}">
                        Vedi tutti <Icon name="arrowRight" :size="15" />
                    </button>
                </div>
            </div>
            <PostsTable :posts="RECENT_POSTS" />
        </div>
    </AppLayout>
</template>
