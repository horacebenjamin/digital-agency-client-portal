<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    outstandingPayments: {
        type: Array,
        default: () => [],
    },
    paidPayments: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Billing" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Billing
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-8 px-4 sm:px-6 lg:px-8">
                <section
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800"
                >
                    <div
                        class="border-b border-gray-200 px-6 py-4 dark:border-gray-700"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Outstanding Payments
                        </h3>
                    </div>

                    <div
                        v-if="outstandingPayments.length === 0"
                        class="p-8 text-center sm:p-10"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200"
                        >
                            BP
                        </div>
                        <h4
                            class="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            No outstanding payments
                        </h4>
                        <p
                            class="mx-auto mt-2 max-w-xl text-sm leading-6 text-gray-600 dark:text-gray-400"
                        >
                            New payment requests from your agency team will
                            appear here when they are ready.
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Payment
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Amount
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Due
                                    </th>
                                    <th class="px-6 py-3" />
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 dark:divide-gray-700"
                            >
                                <tr
                                    v-for="payment in outstandingPayments"
                                    :key="payment.id"
                                >
                                    <td class="px-6 py-4">
                                        <p
                                            class="font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            {{ payment.title }}
                                        </p>
                                        <p
                                            v-if="payment.project_name"
                                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ payment.project_name }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100"
                                    >
                                        {{ payment.amount }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="[
                                                payment.status_badge_classes,
                                                'rounded-full px-3 py-1 text-xs font-medium',
                                            ]"
                                        >
                                            {{ payment.status_label }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{ payment.due_date || 'No due date' }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex justify-end gap-3">
                                            <a
                                                :href="payment.pdf_url"
                                                class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                            >
                                                Download PDF
                                            </a>
                                            <Link
                                                v-if="payment.can_pay"
                                                :href="payment.checkout_url"
                                                method="post"
                                                as="button"
                                                class="inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                                            >
                                                Pay
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800"
                >
                    <div
                        class="border-b border-gray-200 px-6 py-4 dark:border-gray-700"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Paid Payments
                        </h3>
                    </div>

                    <div
                        v-if="paidPayments.length === 0"
                        class="p-8 text-center sm:p-10"
                    >
                        <div
                            class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200"
                        >
                            PP
                        </div>
                        <h4
                            class="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            No paid payments yet
                        </h4>
                        <p
                            class="mx-auto mt-2 max-w-xl text-sm leading-6 text-gray-600 dark:text-gray-400"
                        >
                            Completed payment requests will be kept here for
                            your records.
                        </p>
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <thead class="bg-gray-50 dark:bg-gray-900/40">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Payment
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Amount
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Status
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    >
                                        Paid
                                    </th>
                                    <th class="px-6 py-3" />
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 dark:divide-gray-700"
                            >
                                <tr
                                    v-for="payment in paidPayments"
                                    :key="payment.id"
                                >
                                    <td class="px-6 py-4">
                                        <p
                                            class="font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            {{ payment.title }}
                                        </p>
                                        <p
                                            v-if="payment.project_name"
                                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ payment.project_name }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100"
                                    >
                                        {{ payment.amount }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <span
                                            :class="[
                                                payment.status_badge_classes,
                                                'rounded-full px-3 py-1 text-xs font-medium',
                                            ]"
                                        >
                                            {{ payment.status_label }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            payment.paid_date ||
                                            'Payment received'
                                        }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a
                                            :href="payment.pdf_url"
                                            class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Download Receipt
                                        </a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
