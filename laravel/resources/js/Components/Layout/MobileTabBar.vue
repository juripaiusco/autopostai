<script setup>
// Barra di navigazione fissa in basso, solo mobile (<= 768px, vedi app.css):
// le tre viste principali sempre a un tocco, come in un'app. Il menu completo
// resta nell'hamburger della topbar.
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '@/Components/Icon.vue';

const props = defineProps({
    current: { type: String, required: true },
});

const emit = defineEmits(['navigate']);

const isCreate = computed(() => route().current('posts.create'));
</script>

<template>
    <nav class="tabbar" aria-label="Navigazione principale">
        <Link class="tab" :class="{ active: props.current === 'dashboard' }" :href="route('dashboard')"
              :aria-current="props.current === 'dashboard' ? 'page' : undefined" @click="emit('navigate')">
            <Icon name="dashboard" :size="24" />
            <span>Dashboard</span>
        </Link>

        <Link class="tab tab-new" :class="{ active: isCreate }" :href="route('posts.create')"
              :aria-current="isCreate ? 'page' : undefined" @click="emit('navigate')">
            <span class="tab-new-btn"><Icon name="plus" :size="24" /></span>
            <span>Nuovo post</span>
        </Link>

        <Link class="tab" :class="{ active: props.current === 'posts' && !isCreate }" :href="route('posts')"
              :aria-current="props.current === 'posts' && !isCreate ? 'page' : undefined" @click="emit('navigate')">
            <Icon name="chat" :size="24" />
            <span>Posts</span>
        </Link>
    </nav>
</template>
