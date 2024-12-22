<script setup>

defineProps({
    breadcrumbArray: Array,
})

</script>

<template>

    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li v-for="(breadcrumb, i) in breadcrumbArray"
                class="breadcrumb-item lg:text-xl text-gray-800 dark:text-gray-200"
                :class="{
                'active font-semibold': (i === breadcrumbArray.length - 1),
                '': (i === 0)
                }">

                <span class="hidden sm:inline">
                    {{ breadcrumb }}
                </span>

                <span class="sm:hidden">

                    <span v-if="breadcrumbArray.toString().length <= 42">
                        {{ breadcrumb }}
                    </span>
                    <span v-else>
                        {{ breadcrumb.length > 15 ? breadcrumb.substring(0, 15) + ' ...' : breadcrumb }}
                    </span>

                </span>

            </li>
        </ol>
    </nav>

</template>
