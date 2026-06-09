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
            <DashStatWidget :index="0" icon="calendar" label="Post inviati" :value="g.posts" sub="su tutti i canali" :trend="-4" color="var(--g500)" :spark-data="pSpark" />
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

        <div class="mv2-card">
          <div class="mv2-head">
            <div class="mv2-title">Metriche per canale</div>
            <div class="mv2-sub">Views · utenti unici · commenti · ritorno medio — ultimi 90 giorni</div>
          </div>
          <div class="mv2-body">
            <div class="mv2-bars">
              <div class="mv2-bars-head">Views</div>
              <div class="mv2-bar-row">
                <span class="mv2-bar-icon" style="background:color-mix(in srgb,#1877f2 12%,white)"><svg width="14" height="14" viewBox="0 0 24 24" style="color:#1877f2"><path d="M22 12.06C22 6.5 17.52 2 12 2S2 6.5 2 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.54V9.84c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.45 2.91h-2.33V22c4.78-.76 8.44-4.92 8.44-9.94Z" fill="currentColor"/></svg></span>
                <span class="mv2-bar-label">Facebook</span>
                <div class="mv2-bar-track"><div class="mv2-bar-fill" style="width:68.85%;background:#1877f2"></div></div>
                <span class="mv2-bar-value">12.840</span>
              </div>
              <div class="mv2-bar-row">
                <span class="mv2-bar-icon" style="background:color-mix(in srgb,#e4405f 12%,white)"><svg width="14" height="14" viewBox="0 0 24 24" style="color:#e4405f"><rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg></span>
                <span class="mv2-bar-label">Instagram</span>
                <div class="mv2-bar-track"><div class="mv2-bar-fill" style="width:100%;background:#e4405f"></div></div>
                <span class="mv2-bar-value">18.650</span>
              </div>
              <div class="mv2-bar-row">
                <span class="mv2-bar-icon" style="background:color-mix(in srgb,#0a66c2 12%,white)"><svg width="14" height="14" viewBox="0 0 24 24" style="color:#0a66c2"><path d="M6.94 8.5H3.56V21h3.38V8.5ZM5.25 3a1.97 1.97 0 1 0 0 3.94A1.97 1.97 0 0 0 5.25 3ZM21 21v-6.86c0-3.27-1.75-4.79-4.08-4.79-1.88 0-2.72 1.04-3.19 1.76V8.5H10.4c.05.96 0 12.5 0 12.5h3.33v-6.98c0-.37.03-.75.14-1.02.3-.75 1-1.53 2.16-1.53 1.52 0 2.13 1.16 2.13 2.86V21H21Z" fill="currentColor"/></svg></span>
                <span class="mv2-bar-label">LinkedIn</span>
                <div class="mv2-bar-track"><div class="mv2-bar-fill" style="width:34.48%;background:#0a66c2"></div></div>
                <span class="mv2-bar-value">6.430</span>
              </div>
              <div class="mv2-bar-row">
                <span class="mv2-bar-icon" style="background:color-mix(in srgb,#21759b 12%,white)"><svg width="14" height="14" viewBox="0 0 24 24" style="color:#21759b"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-7.94 8.7 3.36 9.2a8.1 8.1 0 0 1-3.36-9.2Zm7.94-6.84c1.5 0 2.86.34 4.05.95a.74.74 0 0 0-.08 0c-.78 0-1.34.68-1.34 1.4 0 .65.37 1.2.77 1.85.3.53.65 1.2.65 2.18 0 .67-.26 1.46-.6 2.55l-.78 2.62-2.86-8.5a.94.94 0 0 1 .8-.4c.06 0 .12 0 .14-.01a.32.32 0 0 0 .27-.32c0-.18-.16-.32-.36-.31-.6.04-1.49.04-1.49.04s-.93 0-1.55-.04a.34.34 0 0 0-.36.32c0 .16.12.3.27.31.06.01.12.01.18.02.6.07.65.08.94.93l1.3 3.46-1.83 5.49-3.05-8.95c.6-.07.65-.08.65-.34a.32.32 0 0 0-.34-.31s-1.16.09-1.91.09c-.13 0-.29 0-.46-.01A8.16 8.16 0 0 1 12 3.86Zm.51 14.5 2.61-7.32 2.65 6.96a.4.4 0 0 0 .03.06 8.13 8.13 0 0 1-5.29.3Zm6.79-1.92 2.69-7.66.04-.16a8.16 8.16 0 0 1-2.73 7.82Z" fill="currentColor"/></svg></span>
                <span class="mv2-bar-label">WordPress</span>
                <div class="mv2-bar-track"><div class="mv2-bar-fill" style="width:22.46%;background:#21759b"></div></div>
                <span class="mv2-bar-value">4.190</span>
              </div>
              <div class="mv2-bar-row">
                <span class="mv2-bar-icon" style="background:color-mix(in srgb,#6b7280 12%,white)"><svg width="14" height="14" viewBox="0 0 24 24" style="color:#6b7280"><rect x="3" y="5" width="18" height="14" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m4 7 8 6 8-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                <span class="mv2-bar-label">Newsletter</span>
                <div class="mv2-bar-track"><div class="mv2-bar-fill" style="width:17.59%;background:#6b7280"></div></div>
                <span class="mv2-bar-value">3.280</span>
              </div>
            </div>
            <div class="mv2-detail">
              <table>
                <thead>
                  <tr>
                    <th>Canale</th>
                    <th>Utenti unici</th>
                    <th>Commenti</th>
                    <th>Views / post</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td>Facebook</td><td style="text-align:right">4.210</td><td style="text-align:right">234</td><td class="mv2-vpp" style="text-align:right">713</td></tr>
                  <tr><td>Instagram</td><td style="text-align:right">7.820</td><td style="text-align:right">892</td><td class="mv2-vpp" style="text-align:right">848</td></tr>
                  <tr><td>LinkedIn</td><td style="text-align:right">3.640</td><td style="text-align:right">156</td><td class="mv2-vpp" style="text-align:right">536</td></tr>
                  <tr><td>WordPress</td><td style="text-align:right">2.870</td><td style="text-align:right">67</td><td class="mv2-vpp" style="text-align:right">524</td></tr>
                  <tr><td>Newsletter</td><td style="text-align:right">3.120</td><td style="text-align:right"><span style="color:var(--g300)">—</span></td><td class="mv2-vpp" style="text-align:right">547</td></tr>
                </tbody>
              </table>
            </div>
          </div>
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

<style>
/* ── Metriche per canale: layout split barre + tabella ── */
.mv2-card {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,.08), 0 2px 4px -2px rgba(0,0,0,.05);
  margin-bottom: 20px;
  overflow: hidden;
}
.mv2-head {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
}
.mv2-title {
  font: 700 16px/1.3 var(--font-ui);
  color: var(--ink);
}
.mv2-sub {
  font: 400 12px/1.4 var(--font-ui);
  color: var(--g500);
  margin-top: 2px;
}
.mv2-body {
  display: grid;
  grid-template-columns: 1fr 1fr;
}
.mv2-bars {
  padding: 0 20px 14px;
  border-right: 1px solid var(--g100);
}
.mv2-bars-head {
  font: 700 11px/1.3 var(--font-ui);
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--g500);
  background: var(--g50);
  padding: 10px 0;
  margin: 0 -20px;
  padding-left: 20px;
  padding-right: 20px;
}
.mv2-bar-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
}
.mv2-bar-row:not(:last-child) {
  border-bottom: 1px solid var(--g100);
}
.mv2-bar-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.mv2-bar-label {
  font: 600 13px/1.3 var(--font-ui);
  color: var(--ink);
  width: 84px;
  flex-shrink: 0;
}
.mv2-bar-track {
  flex: 1;
  height: 6px;
  background: var(--g100);
  border-radius: 9999px;
  overflow: hidden;
}
.mv2-bar-fill {
  height: 100%;
  border-radius: 9999px;
}
.mv2-bar-value {
  font: 700 13px/1.3 var(--font-ui);
  color: var(--ink);
  width: 50px;
  text-align: right;
  flex-shrink: 0;
}
.mv2-detail {
  overflow-x: auto;
}
.mv2-detail table {
  width: 100%;
  border-collapse: collapse;
}
.mv2-detail thead th {
  font: 700 11px/1.3 var(--font-ui);
  letter-spacing: .05em;
  text-transform: uppercase;
  color: var(--g500);
  background: var(--g50);
  padding: 10px 14px;
  text-align: right;
  white-space: nowrap;
}
.mv2-detail thead th:first-child {
  text-align: left;
  padding-left: 20px;
}
.mv2-detail tbody tr {
  border-top: 1px solid var(--g100);
  transition: background .12s;
}
.mv2-detail tbody tr:hover {
  background: var(--g50);
}
.mv2-detail td {
  padding: 13px 14px;
  font: 500 13px/1.4 var(--font-ui);
  color: var(--g600);
  text-align: right;
  white-space: nowrap;
}
.mv2-detail td:first-child {
  text-align: left;
  padding-left: 20px;
  font: 600 13px/1.4 var(--font-ui);
  color: var(--ink);
}
.mv2-vpp {
  font-weight: 700;
  color: var(--ink);
}
</style>
