<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    tickets: {
        type: Object,
        required: true,
    },
});

const statusBadgeClasses = (status) => {
    return (
        {
            resolved:
                'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
            in_progress:
                'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
            waiting_on_client:
                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            open: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200',
        }[status] ||
        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
    );
};
</script>

<template>
    <Head title="Support Tickets" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    Support Tickets
                </h2>
                <Link
                    :href="route('client.support-tickets.create')"
                    class="inline-flex w-fit items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                >
                    New Ticket
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800"
                >
                    <div
                        v-if="tickets.data.length === 0"
                        class="p-6 text-sm text-gray-600 dark:text-gray-400"
                    >
                        No support tickets have been opened for your account
                        yet.
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Subject
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Priority
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Created
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Latest Activity
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr
                                    v-for="ticket in tickets.data"
                                    :key="ticket.id"
                                >
                                    <td class="px-6 py-4">
                                        <Link
                                            :href="ticket.show_url"
                                            class="font-semibold text-gray-900 hover:text-indigo-700 dark:text-gray-100 dark:hover:text-indigo-300"
                                        >
                                            {{ ticket.subject }}
                                        </Link>
                                        <p
                                            v-if="ticket.project_title"
                                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ ticket.project_title }}
                                        </p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="[
                                                statusBadgeClasses(
                                                    ticket.status,
                                                ),
                                                'rounded-full px-3 py-1 text-xs font-medium',
                                            ]"
                                        >
                                            {{ ticket.status_label }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ ticket.priority_label }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ ticket.created_date }}
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            ticket.latest_activity_date ||
                                            'No replies yet'
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    v-if="tickets.links.length > 3"
                    class="mt-8 flex flex-wrap gap-2"
                >
                    <Link
                        v-for="link in tickets.links"
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
