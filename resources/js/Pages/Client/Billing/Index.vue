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
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold text-slate-950 dark:text-slate-100">
                    Billing
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Review invoices, download PDFs, and pay outstanding requests.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="grid gap-4 sm:grid-cols-2">
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Outstanding</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-slate-100">
                        {{ outstandingPayments.length }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Payment requests awaiting action
                    </p>
                </article>
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Paid</p>
                    <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-slate-100">
                        {{ paidPayments.length }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        Completed payment records
                    </p>
                </article>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-slate-100">
                        Outstanding Payments
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Requests that may need payment or review.
                    </p>
                </div>

                <div v-if="outstandingPayments.length === 0" class="p-8 text-center sm:p-10">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        BP
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-950 dark:text-slate-100">
                        No outstanding payments
                    </h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        New payment requests from your agency team will appear here when they are ready.
                    </p>
                </div>

                <div v-else class="grid gap-4 p-5 lg:grid-cols-2">
                    <article
                        v-for="payment in outstandingPayments"
                        :key="payment.id"
                        class="rounded-lg border border-slate-200 p-5 dark:border-slate-800"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-slate-950 dark:text-slate-100">
                                    {{ payment.title }}
                                </h3>
                                <p v-if="payment.project_name" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    {{ payment.project_name }}
                                </p>
                            </div>
                            <span :class="[payment.status_badge_classes, 'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium']">
                                {{ payment.status_label }}
                            </span>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Amount</p>
                                <p class="mt-1 text-2xl font-bold text-slate-950 dark:text-slate-100">{{ payment.amount }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Due</p>
                                <p class="mt-1 text-sm font-medium text-slate-700 dark:text-slate-300">{{ payment.due_date || 'No due date' }}</p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-3">
                            <a
                                :href="payment.pdf_url"
                                class="inline-flex items-center rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Download PDF
                            </a>
                            <Link
                                v-if="payment.can_pay"
                                :href="payment.checkout_url"
                                method="post"
                                as="button"
                                class="inline-flex items-center rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                Pay
                            </Link>
                        </div>
                    </article>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-slate-100">
                        Paid Payments
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Receipts and paid payment history.
                    </p>
                </div>

                <div v-if="paidPayments.length === 0" class="p-8 text-center sm:p-10">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        PP
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-950 dark:text-slate-100">
                        No paid payments yet
                    </h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Completed payment requests will be kept here for your records.
                    </p>
                </div>

                <div v-else class="divide-y divide-slate-200 dark:divide-slate-800">
                    <article
                        v-for="payment in paidPayments"
                        :key="payment.id"
                        class="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-semibold text-slate-950 dark:text-slate-100">
                                    {{ payment.title }}
                                </h3>
                                <span :class="[payment.status_badge_classes, 'rounded-full px-2.5 py-1 text-xs font-medium']">
                                    {{ payment.status_label }}
                                </span>
                            </div>
                            <p v-if="payment.project_name" class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                {{ payment.project_name }}
                            </p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Paid: {{ payment.paid_date || 'Payment received' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-4">
                            <p class="text-lg font-bold text-slate-950 dark:text-slate-100">
                                {{ payment.amount }}
                            </p>
                            <a
                                :href="payment.pdf_url"
                                class="inline-flex items-center rounded-md border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Download Receipt
                            </a>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
