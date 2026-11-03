<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SectionCard from '@/Components/Layout/SectionCard.vue';
import Icon from '@/Components/Icon.vue';
import FieldRow from '@/Components/UI/FieldRow.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

// Token personali per il server MCP: il plaintext arriva una sola volta
// (flash dopo la creazione), poi resta visibile solo la parte finale.
const props = defineProps({
    mcp: { type: Object, required: true },
});

const ABILITY_HELP = {
    read: "L'assistente legge KPI, post, calendario e contatti. Non modifica nulla.",
    write: "In più prepara bozze e post programmati e genera testi e immagini con l'AI.",
    publish: 'In più può pubblicare subito, eliminare e modificare post già usciti. Usalo solo se ti serve.',
};

const form = reactive({ name: '', ability: 'read', expiry_days: props.mcp.expiryDays[1] ?? props.mcp.expiryDays[0] });
const errors = ref({});
const creating = ref(false);

const revokeTarget = ref(null);
const revoking = ref(false);

const claudeCommand = computed(() => props.mcp.plaintext
    ? `claude mcp add --transport http faper3 ${props.mcp.url} --header "Authorization: Bearer ${props.mcp.plaintext}"`
    : null);

function create() {
    creating.value = true;
    router.post(route('settings.mcp-tokens.store'), form, {
        preserveScroll: true,
        onSuccess: () => { form.name = ''; errors.value = {}; },
        onError: (e) => { errors.value = e; },
        onFinish: () => { creating.value = false; },
    });
}

function revoke() {
    revoking.value = true;
    router.delete(route('settings.mcp-tokens.destroy', revokeTarget.value.id), {
        preserveScroll: true,
        onFinish: () => { revoking.value = false; revokeTarget.value = null; },
    });
}
</script>

<template>
    <SectionCard title="Accesso MCP"
        subtitle="Collega FaPer3 a un assistente AI (Claude Code, Cursor, VS Code…): potrà consultare i tuoi dati e, se lo consenti, preparare post per te. Il token agisce con i tuoi permessi, mai di più.">
        <div v-if="mcp.plaintext" class="acc-hint-inline" style="margin-bottom: 16px; align-items: flex-start">
            <Icon name="key" :size="14" />
            <span style="min-width: 0">
                <b style="display: block; color: var(--g700)">Copia ora il token: non sarà più mostrato.</b>
                <code style="user-select: all; word-break: break-all">{{ mcp.plaintext }}</code>
                <span style="display: block; margin-top: 10px">Per Claude Code, da terminale:</span>
                <code style="user-select: all; word-break: break-all">{{ claudeCommand }}</code>
            </span>
        </div>

        <FieldRow id="mcp-name" label="Nome" help="Per riconoscerlo, es. &quot;Claude Code portatile&quot;." :error="errors.name">
            <input id="mcp-name" v-model="form.name" class="control" type="text" maxlength="100" />
        </FieldRow>
        <FieldRow id="mcp-ability" label="Permessi" :help="ABILITY_HELP[form.ability]" :error="errors.ability">
            <select id="mcp-ability" v-model="form.ability" class="control">
                <option v-for="(label, key) in mcp.abilities" :key="key" :value="key">{{ label }}</option>
            </select>
        </FieldRow>
        <FieldRow id="mcp-expiry" label="Scadenza" :error="errors.expiry_days">
            <select id="mcp-expiry" v-model="form.expiry_days" class="control">
                <option v-for="d in mcp.expiryDays" :key="d" :value="d">{{ d }} giorni</option>
            </select>
        </FieldRow>
        <button type="button" class="btn btn-secondary btn-sm" :disabled="creating || !form.name.trim()" @click="create">
            <Icon name="key" :size="16" />{{ creating ? 'Creazione…' : 'Crea token' }}
        </button>
    </SectionCard>

    <SectionCard title="I tuoi token" subtitle="Revoca subito un token che non usi più o che potrebbe essere finito in mani sbagliate.">
        <div v-if="!mcp.tokens.length" class="acc-hint-inline">
            <Icon name="info" :size="14" />Nessun token creato.
        </div>
        <div v-for="t in mcp.tokens" :key="t.id" class="acc-verify-row" :style="{ opacity: t.state === 'attivo' ? 1 : 0.55 }">
            <span style="min-width: 0">
                <b style="color: var(--g700)">{{ t.name }}</b>
                <span class="acc-hint-inline" style="display: block">
                    {{ t.ability }} · ••••{{ t.lastFour }} · creato {{ t.createdAt }} ·
                    {{ t.state === 'attivo' ? 'scade ' + t.expiresAt : t.state }}
                    <template v-if="t.lastUsedAt"> · ultimo uso {{ t.lastUsedAt }}</template>
                </span>
            </span>
            <button v-if="t.state === 'attivo'" type="button" class="acc-verify" style="margin-left: auto" @click="revokeTarget = t">
                Revoca
            </button>
        </div>
    </SectionCard>

    <Teleport to="body">
        <ConfirmModal v-if="revokeTarget" title="Revoca token MCP" confirm-label="Revoca" pending-label="Revoca…" danger
            :loading="revoking" @cancel="revokeTarget = null" @confirm="revoke">
            L'assistente collegato con «{{ revokeTarget.name }}» perderà subito l'accesso. Non si può annullare.
        </ConfirmModal>
    </Teleport>
</template>
