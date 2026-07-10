<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    tickets: {
        type: Object,
        required: true,
    },
});

const ticketStats = computed(() => {
    const items = props.tickets.data || [];

    return [
        { label: 'Visible Tickets', value: items.length },
        { label: 'Open', value: items.filter((ticket) => ticket.status === 'open').length },
        { label: 'High Priority', value: items.filter((ticket) => ['high', 'urgent'].includes(ticket.priority)).length },
    ];
});
</script>

<template>
    <Head title="Support Tickets" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-950 dark:text-slate-100">
                        Support Tickets
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Keep project questions and agency replies organized.
                    </p>
                </div>
                <Link
                    :href="route('client.support-tickets.create')"
                    class="inline-flex w-fit items-center rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                >
                    New Ticket
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <section class="grid gap-4 sm:grid-cols-3">
                <article
                    v-for="stat in ticketStats"
                    :key="stat.label"
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        {{ stat.label }}
                    </p>
                    <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-slate-100">
                        {{ stat.value }}
                    </p>
                </article>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-slate-100">
                        Ticket Inbox
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Prioritized conversations across your active projects.
                    </p>
                </div>

                <div v-if="tickets.data.length === 0" class="p-8 text-center sm:p-10">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        ST
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-950 dark:text-slate-100">
                        No support tickets yet
                    </h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        When you need help with an active project, open a ticket and the conversation will stay organized here.
                    </p>
                    <Link
                        :href="route('client.support-tickets.create')"
                        class="mt-6 inline-flex items-center rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                    >
                        Create Ticket
                    </Link>
                </div>

                <div v-else>
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead class="bg-slate-50 dark:bg-slate-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Priority</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Created</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Latest Activity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                <tr
                                    v-for="ticket in tickets.data"
                                    :key="ticket.id"
                                    class="transition hover:bg-slate-50 dark:hover:bg-slate-950"
                                >
                                    <td class="px-5 py-4">
                                        <Link
                                            :href="ticket.show_url"
                                            class="font-semibold text-slate-950 hover:text-slate-600 dark:text-slate-100 dark:hover:text-slate-300"
                                        >
                                            {{ ticket.subject }}
                                        </Link>
                                        <p v-if="ticket.project_title" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                            {{ ticket.project_title }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span :class="[ticket.status_badge_classes, 'rounded-full px-2.5 py-1 text-xs font-medium']">
                                            {{ ticket.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-sm font-medium text-slate-700 dark:text-slate-300">
                                        {{ ticket.priority_label }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                        {{ ticket.created_date }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                        {{ ticket.latest_activity_date || 'No replies yet' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-slate-200 lg:hidden dark:divide-slate-800">
                        <article
                            v-for="ticket in tickets.data"
                            :key="`ticket-${ticket.id}`"
                            class="p-5"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <Link :href="ticket.show_url" class="font-semibold text-slate-950 dark:text-slate-100">
                                        {{ ticket.subject }}
                                    </Link>
                                    <p v-if="ticket.project_title" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        {{ ticket.project_title }}
                                    </p>
                                </div>
                                <span :class="[ticket.status_badge_classes, 'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium']">
                                    {{ ticket.status_label }}
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Priority</p>
                                    <p class="mt-1 text-slate-950 dark:text-slate-100">{{ ticket.priority_label }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Latest</p>
                                    <p class="mt-1 text-slate-950 dark:text-slate-100">{{ ticket.latest_activity_date || 'No replies yet' }}</p>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <div v-if="tickets.links.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in tickets.links"
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
