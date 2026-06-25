<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    notifications: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Notifications" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Notifications
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800"
                >
                    <div
                        v-if="notifications.data.length === 0"
                        class="p-6 text-sm text-gray-600 dark:text-gray-400"
                    >
                        You do not have any notifications yet.
                    </div>

                    <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                        <article
                            v-for="notification in notifications.data"
                            :key="notification.id"
                            class="flex flex-col gap-4 p-6 sm:flex-row sm:items-start sm:justify-between"
                            :class="{
                                'bg-indigo-50/60 dark:bg-indigo-950/20':
                                    !notification.read_at,
                            }"
                        >
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3
                                        class="font-semibold text-gray-900 dark:text-gray-100"
                                    >
                                        {{ notification.title }}
                                    </h3>
                                    <span
                                        v-if="!notification.read_at"
                                        class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/60 dark:text-indigo-200"
                                    >
                                        Unread
                                    </span>
                                </div>

                                <p
                                    v-if="notification.body"
                                    class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ notification.body }}
                                </p>
                                <p
                                    v-if="notification.project_title"
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ notification.project_title }}
                                </p>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                    {{ notification.created_at }}
                                </p>
                            </div>

                            <div class="flex shrink-0 flex-wrap gap-3">
                                <Link
                                    v-if="notification.url"
                                    :href="notification.url"
                                    class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    View
                                </Link>
                                <Link
                                    v-if="!notification.read_at"
                                    :href="
                                        route(
                                            'client.notifications.read',
                                            notification.id,
                                        )
                                    "
                                    method="patch"
                                    as="button"
                                    preserve-scroll
                                    class="inline-flex items-center rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                                >
                                    Mark as read
                                </Link>
                            </div>
                        </article>
                    </div>
                </div>

                <div
                    v-if="notifications.links.length > 3"
                    class="mt-8 flex flex-wrap gap-2"
                >
                    <Link
                        v-for="link in notifications.links"
                        :key="`${link.label}-${link.url}`"
                        :href="link.url || '#'"
                        preserve-scroll
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium',
                            link.active
                                ? 'bg-gray-900 text-white dark:bg-gray-100 dark:text-gray-900'
                                : 'bg-white text-gray-700 shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                            !link.url && 'pointer-events-none opacity-50',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
