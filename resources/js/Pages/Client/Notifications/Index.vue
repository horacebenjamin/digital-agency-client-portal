<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    notifications: {
        type: Object,
        required: true,
    },
});

const unreadCount = computed(() => {
    return (props.notifications.data || []).filter((notification) => !notification.read_at).length;
});
</script>

<template>
    <Head title="Notifications" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold text-slate-950 dark:text-slate-100">
                    Notifications
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Project updates, file uploads, and ticket replies from your agency team.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="grid gap-4 sm:grid-cols-2">
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Visible Notifications</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-slate-100">
                        {{ notifications.data.length }}
                    </p>
                </article>
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Unread</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-slate-100">
                        {{ unreadCount }}
                    </p>
                </article>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-slate-100">
                        Notification Inbox
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        New items are highlighted until marked as read.
                    </p>
                </div>

                <div v-if="notifications.data.length === 0" class="p-8 text-center sm:p-10">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        IN
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-950 dark:text-slate-100">
                        No notifications yet
                    </h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Project updates, new files, and support ticket replies will appear here as your agency team shares them.
                    </p>
                </div>

                <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
                    <article
                        v-for="notification in notifications.data"
                        :key="notification.id"
                        class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between"
                        :class="{
                            'bg-cyan-50/70 dark:bg-cyan-950/20': !notification.read_at,
                        }"
                    >
                        <div class="flex min-w-0 gap-3">
                            <span
                                :class="[
                                    'mt-1 h-2.5 w-2.5 shrink-0 rounded-full',
                                    notification.read_at ? 'bg-slate-300 dark:bg-slate-700' : 'bg-cyan-600 dark:bg-cyan-400',
                                ]"
                            />
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-slate-950 dark:text-slate-100">
                                        {{ notification.title }}
                                    </h3>
                                    <span
                                        v-if="!notification.read_at"
                                        class="rounded-full bg-cyan-100 px-2 py-0.5 text-xs font-semibold text-cyan-800 dark:bg-cyan-900/60 dark:text-cyan-200"
                                    >
                                        Unread
                                    </span>
                                </div>

                                <p v-if="notification.body" class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
                                    {{ notification.body }}
                                </p>
                                <p v-if="notification.project_title" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ notification.project_title }}
                                </p>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    {{ notification.created_at }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-3 pl-5 sm:pl-0">
                            <Link
                                v-if="notification.url"
                                :href="notification.url"
                                class="inline-flex items-center rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                View
                            </Link>
                            <Link
                                v-if="!notification.read_at"
                                :href="route('client.notifications.read', notification.id)"
                                method="patch"
                                as="button"
                                preserve-scroll
                                class="inline-flex items-center rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                Mark as read
                            </Link>
                        </div>
                    </article>
                </div>
            </section>

            <div v-if="notifications.links.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in notifications.links"
                    :key="`${link.label}-${link.url}`"
                    :href="link.url || '#'"
                    preserve-scroll
                    :class="[
                        'rounded-md border px-3 py-2 text-sm font-medium',
                        link.active
                            ? 'border-slate-950 bg-slate-950 text-white dark:border-white dark:bg-white dark:text-slate-950'
                            : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                        !link.url && 'pointer-events-none opacity-50',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
