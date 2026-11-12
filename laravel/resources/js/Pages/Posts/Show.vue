<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import StatusPill from '@/Components/StatusPill.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PageHeader from '@/Components/Layout/PageHeader.vue';
import PostChannelCard from '@/Components/Domain/PostChannelCard.vue';
import PostCommentItem from '@/Components/Domain/PostCommentItem.vue';
import RichEditor from '@/Components/Editor/RichEditor.vue';
import SocialEditor from '@/Components/Editor/SocialEditor.vue';
import { CH_CONFIG } from '@/data/dashboardMock';
import { splitArticle, joinArticle, htmlToText } from '@/lib/postText.js';
import { useTokenUnit } from '@/Composables/useTokenUnit';

const { formatTokens, unitTitle, withUnit } = useTokenUnit();

const props = defineProps({
    post: { type: Object, required: true },
    comments: { type: Array, required: true },
});

const userName = computed(() => usePage().props.auth?.user?.name ?? '');

const filter = ref('all');
const showFullContent = ref(false);

const filterTabs = computed(() => [
    { id: 'all', label: 'Tutti', count: props.post.commentsTotal },
    ...props.post.channels.map((id) => ({
        id,
        label: CH_CONFIG[id]?.label ?? id,
        count: props.post.commentsByChannel[id] ?? 0,
    })),
]);

const filteredComments = computed(() => (
    filter.value === 'all' ? props.comments : props.comments.filter((c) => c.channel === filter.value)
));

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Canali su cui la pubblicazione è fallita. Il worker riprova da solo fino a
// post.maxAttempts, poi si ferma: "Riprova" azzera il conteggio.
const channelErrors = computed(() => props.post.channelErrors ?? []);

// Risultati complessivi: per ora l'unica fonte reale oltre ai commenti è la
// newsletter smtp_custom (pixel + email_sends). Views = email aperte, persone
// raggiunte = email consegnate (inviate − rimbalzate). Mailchimp/Brevo e social
// restano N/D finché non arrivano i report/insights (fase F); se il post ha
// anche canali social la cifra è parziale e lo diciamo.
const nlStats = computed(() => {
    const nl = props.post.newsletter;
    return nl && !nl.campaign ? nl : null;
});
const kpiViews = computed(() => nlStats.value?.opened ?? null);
const kpiReach = computed(() => (nlStats.value ? Math.max(0, nlStats.value.sent - nlStats.value.bounced) : null));
const kpiPartial = computed(() => nlStats.value !== null && props.post.channels.some((c) => c !== 'newsletter'));
const kpiNote = computed(() => (kpiPartial.value ? 'Solo newsletter: i dati dei social non sono ancora disponibili.' : 'Dalla newsletter.'));
const retrying = ref(false);

function retry() {
    retrying.value = true;
    router.post(route('posts.retry', props.post.id), {}, {
        preserveScroll: true,
        onFinish: () => { retrying.value = false; },
    });
}

// "Modifica post" dal box di un canale già pubblicato: il testo vale solo per
// quel canale, il worker (posts_update) lo rimanda alla piattaforma.
// WordPress: titolo + editor ricco (HTML); Facebook: editor social (testo).
const editChannel = ref(null);
const editText = ref('');
const editTitle = ref('');
const savingEdit = ref(false);
const editError = ref(null);
const editIsArticle = computed(() => editChannel.value === 'wordpress');

function openEdit(id) {
    const current = props.post.channelEdit?.[id] ?? {};
    editChannel.value = id;
    editError.value = null;
    if (id === 'wordpress') {
        const { title, body } = splitArticle(current.content ?? '');
        editTitle.value = title;
        editText.value = body;
    } else {
        editText.value = current.format === 'html' ? htmlToText(current.content) : (current.content ?? '');
    }
}

function cancelEdit() {
    if (savingEdit.value) return;
    editChannel.value = null;
}

function saveEdit() {
    savingEdit.value = true;
    editError.value = null;
    const payload = editIsArticle.value
        ? { content: editText.value || editTitle.value.trim() ? joinArticle(editTitle.value, editText.value) : '', format: 'html' }
        : { content: editText.value };
    router.put(route('posts.channel-content.update', [props.post.id, editChannel.value]), payload, {
        preserveScroll: true,
        onSuccess: () => { editChannel.value = null; },
        onError: (errors) => { editError.value = errors.content ?? 'Salvataggio non riuscito. Riprova.'; },
        onFinish: () => { savingEdit.value = false; },
    });
}

const deleting = ref(false);
const deleteError = ref(null);
const confirmingDelete = ref(false);

function confirmDelete() {
    deleting.value = true;
    deleteError.value = null;
    router.delete(route('posts.destroy', props.post.id), {
        onSuccess: () => { confirmingDelete.value = false; },
        onError: () => { deleteError.value = 'Eliminazione non riuscita. Riprova.'; },
        onFinish: () => { deleting.value = false; },
    });
}
</script>

<template>
    <Head :title="post.title" />

    <AppLayout :current="'posts'" :user="userName">
        <template #page-header>
            <PageHeader :crumbs="[{ label: 'Post', href: route('posts') }, { label: post.title, current: true }]" />
        </template>

        <div class="ps-head">
            <div class="ps-head__main">
                <div class="ps-head__meta">
                    <StatusPill :status="post.status" :monitoring-until="post.monitoringUntil" :sending="post.newsletter?.sending ?? false" />
                    <span class="ps-head__date">
                        <Icon name="clock" :size="14" /> {{ formatDate(post.publishedAt) }}
                    </span>
                </div>
                <h1 class="ps-head__title">{{ post.title }}</h1>
            </div>
            <div class="ps-head__actions">
                <button type="button" class="btn btn-light btn-sm" style="color: var(--danger)" @click="confirmingDelete = true">
                    <Icon name="trash" :size="15" /> Elimina
                </button>
            </div>
        </div>

        <div v-if="channelErrors.length" class="ps-errors" role="alert">
            <div class="ps-errors__head">
                <strong>Pubblicazione non riuscita su {{ channelErrors.length === 1 ? 'un canale' : `${channelErrors.length} canali` }}</strong>
                <button type="button" class="btn btn-dark btn-sm" :disabled="retrying" @click="retry">
                    <span v-if="retrying" class="btn-spinner" aria-hidden="true"></span>
                    <Icon v-else name="send" :size="14" />{{ retrying ? 'Invio…' : 'Riprova' }}
                </button>
            </div>
            <ul>
                <li v-for="e in channelErrors" :key="e.channel">
                    <strong>{{ CH_CONFIG[e.channel]?.label ?? e.channel }}</strong> —
                    <template v-if="e.gaveUp">fermo dopo {{ e.attempts }} tentativi, non verrà ritentato da solo.</template>
                    <template v-else>tentativo {{ e.attempts }} di {{ post.maxAttempts }}, il prossimo parte entro un minuto.</template>
                    <span class="ps-errors__msg">{{ e.message }}</span>
                </li>
            </ul>
        </div>

        <!-- Hero: immagine + owner + KPI -->
        <div class="card ps-hero">
            <div class="ps-hero__media">
                <img v-if="post.imgUrl" :src="post.imgUrl" alt="" class="ps-hero__img" />
                <div v-else class="ps-hero__placeholder">
                    <Icon name="image" :size="32" />
                </div>
                <div v-if="post.images.length > 1" class="ps-hero__gallery">
                    <img v-for="img in post.images.slice(1)" :key="img.filename" :src="img.url" alt="" />
                </div>
            </div>

            <div class="ps-hero__side">
                <div class="ps-hero__owner">
                    <UserAvatar :name="post.owner.name" :size="42" />
                    <div class="ps-hero__owner-text">
                        <div class="ps-hero__owner-lab">Proprietario</div>
                        <div class="ps-hero__owner-name">{{ post.owner.name }}</div>
                        <div class="ps-hero__owner-email">{{ post.owner.email }}</div>
                    </div>
                    <div class="ps-hero__channels">
                        <span v-for="id in post.channels" :key="id" class="ps-hero__ch"
                            :title="CH_CONFIG[id]?.label ?? id"
                            :style="{ background: `color-mix(in srgb, ${CH_CONFIG[id]?.color ?? '#6b7280'} 12%, white)`, color: CH_CONFIG[id]?.color ?? '#6b7280' }">
                            <ChannelIcon :id="id" :size="16" />
                        </span>
                    </div>
                </div>

                <div>
                    <div class="ps-hero__kpi-title">Risultati complessivi</div>
                    <div class="ps-kpi-grid">
                        <div class="ps-kpi" :title="kpiViews !== null ? `Email aperte. ${kpiNote}` : null">
                            <div class="ps-kpi__lab"><Icon name="eye" :size="12" /> Views</div>
                            <div v-if="kpiViews === null" class="ps-kpi__val is-nd">N/D</div>
                            <template v-else>
                                <div class="ps-kpi__val">{{ kpiViews.toLocaleString('it-IT') }}</div>
                                <div class="ps-kpi__note">{{ kpiPartial ? 'solo newsletter' : 'email aperte' }}</div>
                            </template>
                        </div>
                        <div class="ps-kpi" :title="kpiReach !== null ? `Email consegnate. ${kpiNote}` : null">
                            <div class="ps-kpi__lab"><Icon name="users" :size="12" /> Persone raggiunte</div>
                            <div v-if="kpiReach === null" class="ps-kpi__val is-nd">N/D</div>
                            <template v-else>
                                <div class="ps-kpi__val">{{ kpiReach.toLocaleString('it-IT') }}</div>
                                <div class="ps-kpi__note">{{ kpiPartial ? 'solo newsletter' : 'email consegnate' }}</div>
                            </template>
                        </div>
                        <div class="ps-kpi">
                            <div class="ps-kpi__lab">Reazioni</div>
                            <div class="ps-kpi__val is-nd">N/D</div>
                        </div>
                        <div class="ps-kpi">
                            <div class="ps-kpi__lab"><Icon name="chat" :size="12" /> Commenti</div>
                            <div class="ps-kpi__val">{{ post.commentsTotal.toLocaleString('it-IT') }}</div>
                        </div>
                    </div>
                </div>

                <div class="ps-hero__sub-row">
                    <div>
                        <div class="ps-hero__sub-lab">Condivisioni</div>
                        <div class="ps-hero__sub-val is-nd">N/D</div>
                    </div>
                    <div>
                        <div class="ps-hero__sub-lab">Salvataggi</div>
                        <div class="ps-hero__sub-val is-nd">N/D</div>
                    </div>
                    <div>
                        <div class="ps-hero__sub-lab">{{ unitTitle }} AI usati</div>
                        <div class="ps-hero__sub-val">{{ formatTokens(post.totalTokens) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance per canale -->
        <div class="ps-section-head">
            <h2>Performance per canale</h2>
            <p>Metriche ricevute da ogni piattaforma su cui hai pubblicato.</p>
        </div>
        <div class="ps-ch-grid">
            <PostChannelCard
                v-for="id in post.channels"
                :key="id"
                :channel-id="id"
                :status="post.channelStatus?.[id] ?? post.status"
                :published-at="post.publishedAt"
                :comments-count="post.commentsByChannel[id] ?? 0"
                :edit="post.channelEdit?.[id]"
                :newsletter="id === 'newsletter' ? post.newsletter : null"
                @edit="openEdit(id)"
            />
        </div>

        <!-- Contenuto -->
        <div class="ps-section-head">
            <h2>Contenuto</h2>
            <p>Il prompt inviato all'AI e il testo generato che è stato pubblicato.</p>
        </div>
        <div class="ps-content-grid">
            <div class="card card-pad ps-prompt-card">
                <div class="ps-card-head">
                    <span class="ps-card-icon"><Icon name="pencil" :size="13" /></span>
                    <span class="ps-card-title">Prompt originale</span>
                </div>
                <div class="ps-prompt-text">"{{ post.prompt || 'Nessun prompt inserito.' }}"</div>
                <div class="ps-prompt-chips">
                    <span v-if="post.commentsEnabled" class="ps-chip"><Icon name="chat" :size="12" /> Commenti abilitati</span>
                    <span v-if="post.autoReplyEnabled" class="ps-chip"><Icon name="sparkles" :size="12" /> Auto-risposta AI</span>
                </div>
            </div>

            <div class="card card-pad">
                <div class="ps-card-head ps-card-head--between">
                    <div class="ps-card-head">
                        <span class="ps-card-icon ps-card-icon--sky"><Icon name="sparkles" :size="14" /></span>
                        <span class="ps-card-title">Contenuto generato dall'AI</span>
                    </div>
                    <span class="ps-token-badge">{{ withUnit(post.postTokens) }}</span>
                </div>
                <!-- HTML già sanificato dal server (PostController::show, Post::contentHtml) -->
                <div v-if="post.aiContentHtml" class="rich-text ps-ai-rich" :class="{ 'is-clamped': !showFullContent }" v-html="post.aiContentHtml"></div>
                <div v-else class="ps-ai-text" :class="{ 'is-clamped': !showFullContent }">{{ post.aiContent }}</div>
                <button type="button" class="ps-readmore" @click="showFullContent = !showFullContent">
                    {{ showFullContent ? 'Mostra meno' : 'Leggi tutto' }}
                    <Icon name="chevron" :size="13" :style="{ transform: showFullContent ? 'rotate(180deg)' : 'rotate(0)' }" />
                </button>
            </div>
        </div>

        <!-- Commenti -->
        <div class="ps-comments-head">
            <div class="ps-section-head" style="margin-bottom: 0">
                <h2>Commenti ricevuti <span class="ps-comments-count">{{ post.commentsTotal }}</span></h2>
                <p>Commenti dei canali e risposte AI inviate.</p>
            </div>
            <div class="ps-filters">
                <button v-for="opt in filterTabs" :key="opt.id" type="button"
                    class="ps-filter" :class="{ 'is-active': filter === opt.id }"
                    @click="filter = opt.id">
                    {{ opt.label }} <span class="ps-filter__count">{{ opt.count }}</span>
                </button>
            </div>
        </div>

        <div v-if="!post.commentsEnabled" class="card card-pad ps-empty">
            <Icon name="chat" :size="28" />
            Il monitoraggio dei commenti è disattivato per questo post.
        </div>
        <div v-else-if="filteredComments.length === 0" class="card card-pad ps-empty">
            <Icon name="chat" :size="28" />
            Nessun commento per questo filtro.
        </div>
        <div v-else class="ps-comments-list">
            <PostCommentItem v-for="c in filteredComments" :key="c.id" :comment="c" />
        </div>

        <!-- Footer -->
        <div class="ps-footer">
            <Link :href="route('posts')" class="btn btn-light btn-sm">← Torna all'elenco post</Link>
            <span class="ps-footer__meta">Post #{{ post.id }} · creato il {{ formatDate(post.createdAt) }}</span>
        </div>

        <ConfirmModal
            v-if="editChannel"
            :title="`Modifica post su ${CH_CONFIG[editChannel]?.label ?? editChannel}`"
            confirm-label="Salva e aggiorna"
            pending-label="Salvataggio…"
            wide
            :loading="savingEdit"
            :error="editError"
            @cancel="cancelEdit"
            @confirm="saveEdit"
        >
            <span class="acc-row-help" style="display:block;margin-bottom:10px">
                Cambia solo questo canale: entro un minuto il post viene aggiornato sulla piattaforma.
            </span>
            <template v-if="editIsArticle">
                <label class="sr-only" for="ps-edit-title">Titolo dell'articolo</label>
                <input id="ps-edit-title" v-model="editTitle" class="control pf-article-title" type="text"
                    placeholder="Titolo dell'articolo" :disabled="savingEdit" />
                <RichEditor v-model="editText" :disabled="savingEdit" :user-id="post.ownerId" />
            </template>
            <SocialEditor v-else v-model="editText" :disabled="savingEdit" :user-id="post.ownerId" />
        </ConfirmModal>

        <ConfirmModal
            v-if="confirmingDelete"
            title="Elimina post"
            confirm-label="Elimina"
            pending-label="Eliminazione…"
            danger
            :loading="deleting"
            :error="deleteError"
            @cancel="confirmingDelete = false"
            @confirm="confirmDelete"
        >
            Sei sicuro di voler eliminare <b>{{ post.title }}</b>?<br />
            <span class="modal-note">Questa azione è irreversibile.</span>
        </ConfirmModal>
    </AppLayout>
</template>
