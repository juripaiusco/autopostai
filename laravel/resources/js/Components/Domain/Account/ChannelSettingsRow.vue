<script setup>
import ChannelIcon from '@/Components/ChannelIcon.vue';
import Icon from '@/Components/Icon.vue';
import ConnectionBadge from '@/Components/UI/ConnectionBadge.vue';
import ToggleSwitch from '@/Components/UI/ToggleSwitch.vue';
import NumberStepper from '@/Components/UI/NumberStepper.vue';
import OptionRow from '@/Components/UI/OptionRow.vue';

const props = defineProps({
    channel: { type: Object, required: true }, // { id, label, ic, kind, meta }
    copy: { type: Object, required: true }, // CH_COPY[channel.kind]
    on: { type: Boolean, required: true },
    replyOn: { type: Boolean, default: null },
    replyN: { type: Number, default: 5 },
    connectionState: { type: String, required: true }, // 'ok' | 'off'
});

const emit = defineEmits(['update:on', 'toggle-reply', 'update:reply-n', 'go-to-integration']);

const statusText = () => props.on
    ? (props.replyOn ? props.copy.optTitle + ' · attivo' : 'Pubblicazione attiva')
    : props.channel.meta;
</script>

<template>
    <div class="acc-ch" :class="{ on }">

        <!-- Channel header (toggle row) -->
        <div class="acc-ch-head" role="switch" :aria-checked="on" tabindex="0"
            :aria-label="'Attiva ' + channel.label"
            @click="emit('update:on', !on)"
            @keydown.enter.prevent="emit('update:on', !on)"
            @keydown.space.prevent="emit('update:on', !on)">
            <div :class="['acc-ch-ic', channel.ic, !on ? 'off' : '']">
                <ChannelIcon :id="channel.id" :size="20" />
            </div>
            <div class="acc-ch-grow">
                <div :class="['acc-ch-name', !on ? 'off' : '']">{{ channel.label }}</div>
                <div class="acc-ch-meta">{{ statusText() }}</div>
            </div>
            <div class="acc-ch-right" @click.stop>
                <ConnectionBadge v-if="on" :state="connectionState" />
                <span v-else class="acc-ch-off-tag">Non attivo</span>
                <ToggleSwitch :model-value="on" @update:model-value="emit('update:on', $event)" />
            </div>
        </div>

        <!-- Channel body (expanded when enabled) -->
        <div v-if="on" class="acc-ch-body acc-reveal">

            <!-- Not connected warning -->
            <OptionRow v-if="connectionState !== 'ok'" variant="warning"
                title="Canale non ancora collegato" help="Collega le credenziali in AI &amp; Integrazioni per poter pubblicare.">
                <template #control>
                    <button type="button" class="acc-verify" @click="emit('go-to-integration')">
                        <Icon name="link" :size="15" />
                        Vai a Integrazioni
                    </button>
                </template>
            </OptionRow>

            <!-- Connected: what this channel can do -->
            <OptionRow v-else variant="success" title="Canale collegato" :help="channel.meta" />

            <!-- Comments/replies option -->
            <OptionRow :title="copy.optTitle" :help="copy.optHelp">
                <template #control>
                    <ToggleSwitch :model-value="replyOn" @update:model-value="emit('toggle-reply', $event)" />
                </template>

                <!-- Reply count stepper (when replies enabled) -->
                <div v-if="replyOn" class="acc-count-field acc-reveal">
                    <div class="acc-ch-opt-row">
                        <div class="acc-ch-opt-txt">
                            <div class="acc-ch-opt-title" style="font-weight:500;color:var(--g600)">{{ copy.countTitle }}</div>
                            <div class="acc-ch-opt-help">{{ copy.countHelp }}</div>
                        </div>
                        <div class="acc-count">
                            <NumberStepper :model-value="replyN ?? 5" :min="1" :max="200"
                                @update:model-value="emit('update:reply-n', $event)" />
                        </div>
                    </div>
                </div>
            </OptionRow>
        </div>
    </div>
</template>
