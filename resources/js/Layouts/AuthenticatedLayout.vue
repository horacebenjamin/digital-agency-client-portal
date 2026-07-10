<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-100">
        <aside
            class="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-slate-200 bg-white/95 lg:flex lg:flex-col dark:border-slate-800 dark:bg-slate-950/95"
        >
            <div class="flex h-16 items-center gap-3 border-b border-slate-200 px-5 dark:border-slate-800">
                <Link
                    :href="route('dashboard')"
                    class="flex min-w-0 items-center gap-3"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-slate-950 text-white dark:border-slate-700 dark:bg-white dark:text-slate-950">
                        <ApplicationLogo class="h-5 w-5 fill-current" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-semibold">Digital Agency</span>
                        <span class="block truncate text-xs text-slate-500 dark:text-slate-400">Client Portal</span>
                    </span>
                </Link>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4">
                <Link
                    :href="route('dashboard')"
                    :class="[
                        'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                        route().current('dashboard')
                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white',
                    ]"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m3 11 9-8 9 8" />
                        <path d="M5 10v10h14V10" />
                        <path d="M9 20v-6h6v6" />
                    </svg>
                    Dashboard
                </Link>
                <Link
                    :href="route('client.projects.index')"
                    :class="[
                        'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                        route().current('client.projects.*')
                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white',
                    ]"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M4 7h16" />
                        <path d="M4 7a2 2 0 0 1 2-2h3l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z" />
                        <path d="M8 13h8" />
                    </svg>
                    My Projects
                </Link>
                <Link
                    :href="route('client.support-tickets.index')"
                    :class="[
                        'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                        route().current('client.support-tickets.*')
                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white',
                    ]"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" />
                        <path d="M8 9h8" />
                        <path d="M8 13h5" />
                    </svg>
                    Support Tickets
                </Link>
                <Link
                    :href="route('client.billing.index')"
                    :class="[
                        'group flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                        route().current('client.billing.*')
                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white',
                    ]"
                >
                    <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path d="M3 10h18" />
                        <path d="M7 15h4" />
                    </svg>
                    Billing
                </Link>
                <Link
                    :href="route('client.notifications.index')"
                    :class="[
                        'group flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium transition',
                        route().current('client.notifications.*')
                            ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white',
                    ]"
                >
                    <span class="flex items-center gap-3">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                            <path d="M10 21h4" />
                        </svg>
                        Notifications
                    </span>
                    <span
                        v-if="$page.props.auth.unreadNotificationsCount > 0"
                        class="rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-200"
                    >
                        {{ $page.props.auth.unreadNotificationsCount }}
                    </span>
                </Link>
            </nav>

            <div class="border-t border-slate-200 p-3 dark:border-slate-800">
                <Dropdown
                    align="right"
                    width="48"
                    direction="up"
                    content-classes="py-1 bg-white dark:bg-slate-900"
                >
                    <template #trigger>
                        <button
                            type="button"
                            class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition hover:bg-slate-100 dark:hover:bg-slate-900"
                        >
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                {{ $page.props.auth.user.name.charAt(0) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-slate-900 dark:text-slate-100">
                                    {{ $page.props.auth.user.name }}
                                </span>
                                <span class="block truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $page.props.auth.user.email }}
                                </span>
                            </span>
                            <span class="text-slate-400">...</span>
                        </button>
                    </template>

                    <template #content>
                        <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">
                            Log Out
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </aside>

        <div class="lg:pl-72">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border border-slate-200 p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 lg:hidden dark:border-slate-800 dark:hover:bg-slate-900 dark:hover:text-white"
                        @click="showingNavigationDropdown = !showingNavigationDropdown"
                    >
                        <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path
                                v-if="!showingNavigationDropdown"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                            <path
                                v-else
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>

                    <div class="min-w-0 flex-1">
                        <slot name="header" />
                    </div>

                    <div class="hidden items-center gap-3 sm:flex">
                        <Link
                            :href="route('client.notifications.index')"
                            class="relative rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white"
                        >
                            Notifications
                            <span
                                v-if="$page.props.auth.unreadNotificationsCount > 0"
                                class="ml-2 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-200"
                            >
                                {{ $page.props.auth.unreadNotificationsCount }}
                            </span>
                        </Link>
                    </div>
                </div>

                <div
                    :class="{ block: showingNavigationDropdown, hidden: !showingNavigationDropdown }"
                    class="border-t border-slate-200 bg-white lg:hidden dark:border-slate-800 dark:bg-slate-950"
                >
                    <div class="space-y-1 pb-3 pt-2">
                        <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
                            Dashboard
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('client.projects.index')" :active="route().current('client.projects.*')">
                            My Projects
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('client.support-tickets.index')" :active="route().current('client.support-tickets.*')">
                            Support Tickets
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('client.billing.index')" :active="route().current('client.billing.*')">
                            Billing
                        </ResponsiveNavLink>
                        <ResponsiveNavLink :href="route('client.notifications.index')" :active="route().current('client.notifications.*')">
                            Notifications
                            <span
                                v-if="$page.props.auth.unreadNotificationsCount > 0"
                                class="ms-2 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-950 dark:text-rose-200"
                            >
                                {{ $page.props.auth.unreadNotificationsCount }}
                            </span>
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-slate-200 pb-1 pt-4 dark:border-slate-800">
                        <div class="px-4">
                            <div class="text-base font-medium text-slate-900 dark:text-slate-100">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-slate-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">Profile</ResponsiveNavLink>
                            <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>
