<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import ProgressBar from "@/Components/ProgressBar.vue";

const showingNavigationDropdown = ref(false);
</script>

<template>

    <div>
        <div class="min-h-screen bg-white lg:bg-gray-100 dark:bg-gray-800 lg:dark:bg-gray-900">
            <nav
                class="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <!-- Primary Navigation Menu -->
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex">
                            <!-- Logo -->
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-9 fill-current text-gray-800 dark:text-gray-200"
                                    />
                                </Link>
                            </div>

                            <!-- Navigation Links -->
                            <div
                                class="hidden sm:-my-px sm:ml-10 sm:flex"
                            >
                                <!-- <NavLink
                                    class="w-[100px] text-center"
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Dashboard
                                </NavLink> -->

                                <NavLink v-if="!$page.props.auth.user.parent_id || $page.props.auth.user.child_on"
                                         class="w-[100px] text-center"
                                         :href="route('user.index')"
                                         :active="route().current().search('user') === 0 ? true : false">
                                    Account
                                </NavLink>

                                <NavLink class="w-[100px] text-center"
                                         :href="route('post.index') + '?orderby=published_at&ordertype=desc&s='"
                                         :active="route().current().search('post') === 0 ? true : false">
                                    Posts
                                </NavLink>

                                <NavLink v-if="$page.props.auth.user.parent_id && !$page.props.auth.user.child_on"
                                         class="w-[100px] text-center"
                                         :href="route('settings.index')"
                                         :active="route().current().search('settings') === 0 ? true : false">
                                    Impostazioni
                                </NavLink>

                            </div>
                        </div>

                        <div v-if="$page.props.auth.user.parent_id && !$page.props.auth.user.child_on"
                             class="sm:hidden text-center mt-3 w-1/2">

                            <label class="text-xs dark:text-gray-400">Token utilizzati</label>
                            <ProgressBar
                                :percent="$page.props.auth.tokens_used / $page.props.auth.user.tokens_limit * 100" />

                        </div>

                        <div class="hidden sm:ms-6 sm:flex sm:items-center">
                            <!-- Settings Dropdown -->
                            <div class="relative ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                            >
                                                {{ $page.props.auth.user.name }}
                                                <span v-if="$page.props.auth.user.child_on">
                                                    &nbsp;-&nbsp;Manager
                                                </span>
                                                <span v-if="!$page.props.auth.user.parent_id">
                                                    &nbsp;-&nbsp;Amministratore
                                                </span>

                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>

                                        <div v-if="$page.props.auth.user.parent_id && !$page.props.auth.user.child_on"
                                             class="items-center w-[100%]">

                                            <ProgressBar
                                                :percent="$page.props.auth.tokens_used / $page.props.auth.user.tokens_limit * 100" />

                                        </div>

                                    </template>

                                    <template #content>
                                        <DropdownLink
                                            :href="route('profile.edit')"
                                        >
                                            Profilo
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Esci
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Hamburger -->
                        <div class="-me-2 flex items-center sm:hidden">
                            <button
                                @click="
                                    showingNavigationDropdown =
                                        !showingNavigationDropdown
                                "
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:text-gray-400"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex':
                                                !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex':
                                                showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <!-- <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Dashboard
                        </ResponsiveNavLink> -->
                        <ResponsiveNavLink v-if="!$page.props.auth.user.parent_id || $page.props.auth.user.child_on"
                                           :href="route('user.index')"
                                           :active="route().current().search('user') === 0 ? true : false">
                            Account
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('post.index') + '?orderby=published_at&ordertype=desc&s='"
                                           :active="route().current().search('post') === 0 ? true : false">
                            Posts
                        </ResponsiveNavLink>
                        <ResponsiveNavLink v-if="$page.props.auth.user.parent_id && !$page.props.auth.user.child_on"
                                 :href="route('settings.index')"
                                 :active="route().current().search('settings') === 0 ? true : false">
                            Impostazioni
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div
                        class="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600"
                    >
                        <div class="px-4">
                            <div
                                class="text-base font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header
                class="bg-white shadow dark:bg-gray-800"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main class="mb-2 sm:mb-0">
                <slot />
            </main>
        </div>

        <!-- Movile botton Nav -->
        <nav class="fixed bottom-[-2px] w-full py-2 pb-8 bg-gray-100 text-gray-400 dark:bg-gray-900 dark:text-gray-600 sm:hidden">
            <div class="container mx-auto flex justify-around">

                <Link v-if="!$page.props.auth.user.parent_id || $page.props.auth.user.child_on"
                         class="px-4 py-2"
                         :href="route('user.index')"
                         :class="{
                             '!text-black dark:!text-gray-300': route().current().search('user') === 0
                         }">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                    </svg>
                </Link>

                <Link class="px-4 py-2"
                      :href="route('post.index') + '?orderby=published_at&ordertype=desc&s='"
                      :class="{
                          '!text-black dark:!text-gray-300': ['post.index', 'post.edit', 'post.show'].some(page => route().current().startsWith(page))
                      }">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                    </svg>
                </Link>

                <Link class="px-4 py-2"
                      :href="route('post.create')"
                      :class="{
                          '!text-black dark:!text-gray-300': route().current().search('post.create') === 0
                      }">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </Link>

                <Link v-if="$page.props.auth.user.parent_id && !$page.props.auth.user.child_on"
                      class="px-4 py-2"
                      :href="route('settings.index')"
                      :class="{
                          '!text-black dark:!text-gray-300': route().current().search('settings') === 0
                      }">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                    </svg>
                </Link>

            </div>
        </nav>
    </div>
</template>
