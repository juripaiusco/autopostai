<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';

const props = defineProps({
    month: { type: String, required: true }, // 'YYYY-MM'
    monthLabel: { type: String, required: true },
    prevMonth: { type: String, required: true },
    nextMonth: { type: String, required: true },
    showAuthor: { type: Boolean, default: false },
    posts: { type: Array, required: true },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? 'Mario Rossi');

const WEEKDAYS = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

const postsByDay = computed(() => {
    const map = {};
    for (const p of props.posts) {
        (map[p.day] ??= []).push(p);
    }
    return map;
});

// Griglia 6x7: calcolata lato client (puro layout, nessun dato di business)
// a partire dai post gia' scoped/filtrati arrivati da ScheduleController.
const weeks = computed(() => {
    const [y, m] = props.month.split('-').map(Number);
    const first = new Date(y, m - 1, 1);
    const startOffset = (first.getDay() + 6) % 7; // lun=0 invece di dom=0
    const gridStart = new Date(y, m - 1, 1 - startOffset);
    const todayIso = new Date().toISOString().slice(0, 10);

    const cells = [];
    for (let i = 0; i < 42; i++) {
        const d = new Date(gridStart);
        d.setDate(gridStart.getDate() + i);
        const iso = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        cells.push({
            iso,
            day: d.getDate(),
            inMonth: d.getMonth() === m - 1,
            isToday: iso === todayIso,
            posts: postsByDay.value[iso] ?? [],
        });
    }

    const w = [];
    for (let i = 0; i < 6; i++) w.push(cells.slice(i * 7, i * 7 + 7));
    return w;
});

function goToMonth(month) {
    router.get(route('schedule'), { month }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Calendarizza" />
    <AppLayout current="schedule" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Calendarizza', current: true }]">
                <template #actions>
                    <div class="sched-nav">
                        <button type="button" class="btn btn-secondary btn-sm" @click="goToMonth(prevMonth)">
                            <Icon name="chevron" :size="15" style="transform: rotate(90deg)" />
                        </button>
                        <span class="sched-month-label">{{ monthLabel }}</span>
                        <button type="button" class="btn btn-secondary btn-sm" @click="goToMonth(nextMonth)">
                            <Icon name="chevron" :size="15" style="transform: rotate(-90deg)" />
                        </button>
                    </div>
                </template>
            </PageHeader>
        </template>

        <div class="card card-pad sched-calendar">
            <div class="sched-weekdays">
                <span v-for="w in WEEKDAYS" :key="w">{{ w }}</span>
            </div>
            <div v-for="(week, wi) in weeks" :key="wi" class="sched-week">
                <div
                    v-for="cell in week"
                    :key="cell.iso"
                    class="sched-cell"
                    :class="{ 'sched-cell--out': !cell.inMonth, 'sched-cell--today': cell.isToday }"
                >
                    <div class="sched-cell-day">{{ cell.day }}</div>
                    <div class="sched-cell-posts">
                        <Link
                            v-for="p in cell.posts.slice(0, 3)"
                            :key="p.id"
                            :href="p.url"
                            class="sched-chip"
                            :title="showAuthor ? `${p.title} — ${p.author}` : p.title"
                        >
                            <ChannelIcon v-if="p.channels[0]" :id="p.channels[0]" :size="12" />
                            <span class="sched-chip-title">{{ p.time }} · {{ p.title }}</span>
                        </Link>
                        <span v-if="cell.posts.length > 3" class="sched-chip-more">+{{ cell.posts.length - 3 }} altri</span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
