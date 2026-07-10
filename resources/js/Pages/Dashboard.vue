<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    summaryCards: {
        type: Array,
        default: () => [],
    },
    recentActivity: {
        type: Array,
        default: () => [],
    },
    focusProject: {
        type: Object,
        default: null,
    },
    latestUpdates: {
        type: Array,
        default: () => [],
    },
    latestFiles: {
        type: Array,
        default: () => [],
    },
});

const summaryTotal = computed(() => {
    return props.summaryCards.reduce((total, card) => total + Number(card.value || 0), 0);
});

const summaryValue = (label) => {
    return Number(props.summaryCards.find((card) => card.label === label)?.value || 0);
};

const openTicketsCount = computed(() => summaryValue('Open Support Tickets'));

const latestActivityDate = computed(() => {
    return props.recentActivity[0]?.date || 'No recent activity';
});

const latestFileDownloadUrl = computed(() => {
    return props.latestFiles[0]?.download_url || null;
});

const healthProgress = computed(() => {
    return props.focusProject?.progress_percentage || 0;
});

const visualSummary = computed(() => {
    return props.summaryCards.map((card, index) => ({
        ...card,
        lineTone: [
            '#0f172a',
            '#0891b2',
            '#f43f5e',
            '#f59e0b',
            '#059669',
        ][index % 5],
        points: [
            0,
            Math.max(4, Number(card.value || 0) * 0.35 + 2),
            Math.max(2, Number(card.value || 0) * 0.2 + index + 1),
            Math.max(6, Number(card.value || 0) * 0.45 + 3),
            Math.max(3, Number(card.value || 0) * 0.3 + index),
            Math.max(7, Number(card.value || 0) * 0.55 + 2),
            Math.max(5, Number(card.value || 0) * 0.42 + 4),
            Math.max(9, Number(card.value || 0) * 0.68 + 3),
            Math.max(8, Number(card.value || 0) * 0.58 + index + 3),
            Math.max(10, Number(card.value || 0) * 0.78 + 4),
            Math.max(9, Number(card.value || 0) * 0.7 + index + 6),
            Math.max(12, Number(card.value || 0) * 0.86 + 5),
        ],
    }));
});

const sparklinePoints = (points) => {
    const maxPoint = Math.max(...points, 1);

    return points
        .map((point, index) => {
            const x = (index / (points.length - 1)) * 180;
            const y = 48 - (point / maxPoint) * 40;

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
};

const projectChartPoints = computed(() => {
    const progress = props.focusProject?.progress_percentage || 0;
    const points = [
        8,
        Math.max(14, progress * 0.22),
        Math.max(18, progress * 0.32),
        Math.max(28, progress * 0.48),
        Math.max(34, progress * 0.62),
        Math.max(42, progress * 0.76),
        Math.max(50, progress),
    ];

    return points
        .map((point, index) => {
            const x = (index / (points.length - 1)) * 240;
            const y = 132 - (point / 100) * 112;

            return `${x.toFixed(1)},${y.toFixed(1)}`;
        })
        .join(' ');
});

const projectAreaPath = computed(() => {
    if (!projectChartPoints.value) {
        return '';
    }

    return `M${projectChartPoints.value.replaceAll(' ', ' L')} L240,132 L0,132 Z`;
});

const activityDotClass = (type) => {
    return (
        {
            'Ticket Reply': 'bg-slate-950 dark:bg-slate-100',
            'Project File': 'bg-cyan-600 dark:bg-cyan-400',
            'Project Update': 'bg-emerald-600 dark:bg-emerald-400',
        }[type] || 'bg-blue-600 dark:bg-blue-400'
    );
};
</script>

<template>
    <Head title="Client Portal" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold tracking-normal text-slate-950 dark:text-slate-100">
                    Welcome back, {{ $page.props.auth.user.name.split(' ')[0] }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Here is what is happening with your projects and account today.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="grid gap-4 xl:grid-cols-5">
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">
                            Overall Project Health
                        </h2>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                            Good
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Everything is progressing well.
                    </p>

                    <div class="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="flex gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="4" width="18" height="18" rx="2" />
                                    <path d="M16 2v4" />
                                    <path d="M8 2v4" />
                                    <path d="M3 10h18" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Latest activity
                                </p>
                                <p class="mt-1 truncate text-sm font-bold text-slate-950 dark:text-slate-100">
                                    {{ latestActivityDate }}
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-300">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" />
                                    <path d="M8 9h8" />
                                    <path d="M8 13h5" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Open tickets
                                </p>
                                <p class="mt-1 text-sm font-bold text-slate-950 dark:text-slate-100">
                                    {{ openTicketsCount }} awaiting reply
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-950 dark:text-violet-300">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="3" y="5" width="18" height="14" rx="2" />
                                    <path d="M3 10h18" />
                                    <path d="M7 15h4" />
                                </svg>
                            </span>
                            <div>
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Payments
                                </p>
                                <p class="mt-1 text-sm font-bold text-slate-950 dark:text-slate-100">
                                    Review billing
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 19V5" />
                                    <path d="M4 19h16" />
                                    <path d="m7 15 4-4 3 3 5-7" />
                                    <path d="M15 7h4v4" />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
                                    Overall progress
                                </p>
                                <p class="mt-1 text-sm font-bold text-slate-950 dark:text-slate-100">
                                    {{ healthProgress }}%
                                </p>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${healthProgress}%` }" />
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-slate-100">
                        Quick Actions
                    </h2>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <Link
                            :href="route('client.projects.index')"
                            class="inline-flex items-center justify-center gap-2 rounded-md bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 7h16" />
                                <path d="M4 7a2 2 0 0 1 2-2h3l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z" />
                            </svg>
                            View Projects
                        </Link>
                        <Link
                            :href="route('client.support-tickets.create')"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" />
                                <path d="M8 9h8" />
                            </svg>
                            New Support Ticket
                        </Link>
                        <a
                            v-if="latestFileDownloadUrl"
                            :href="latestFileDownloadUrl"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3v12" />
                                <path d="m7 10 5 5 5-5" />
                                <path d="M5 21h14" />
                            </svg>
                            Download Files
                        </a>
                        <button
                            v-else
                            type="button"
                            disabled
                            class="inline-flex cursor-not-allowed items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-900"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3v12" />
                                <path d="m7 10 5 5 5-5" />
                                <path d="M5 21h14" />
                            </svg>
                            Download Files
                        </button>
                        <Link
                            :href="route('client.billing.index')"
                            class="inline-flex items-center justify-center gap-2 rounded-md border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="M3 10h18" />
                                <path d="M7 15h4" />
                            </svg>
                            Make a Payment
                        </Link>
                    </div>
                </article>
            </section>

            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <article
                    v-for="(card, index) in summaryCards"
                    :key="card.label"
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                            {{ card.label }}
                        </p>
                        <span
                            :class="[
                                'flex h-8 w-8 items-center justify-center rounded-md text-xs font-semibold',
                                index === 0
                                    ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                                    : 'bg-slate-50 text-slate-500 dark:bg-slate-950 dark:text-slate-400',
                            ]"
                        >
                            <svg
                                v-if="card.label === 'Active Projects'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M4 7h16" />
                                <path d="M4 7a2 2 0 0 1 2-2h3l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z" />
                                <path d="M8 13h8" />
                            </svg>
                            <svg
                                v-else-if="card.label === 'Open Support Tickets'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z" />
                                <path d="M8 9h8" />
                                <path d="M8 13h5" />
                            </svg>
                            <svg
                                v-else-if="card.label === 'Unread Notifications'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                                <path d="M10 21h4" />
                            </svg>
                            <svg
                                v-else-if="card.label === 'Recent Project Updates'"
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M4 19V5" />
                                <path d="M4 19h16" />
                                <path d="m7 15 4-4 3 3 5-7" />
                                <path d="M15 7h4v4" />
                            </svg>
                            <svg
                                v-else
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                                <path d="M14 2v6h6" />
                                <path d="M8 13h8" />
                                <path d="M8 17h5" />
                            </svg>
                        </span>
                    </div>
                    <p class="mt-3 text-3xl font-bold text-slate-950 dark:text-slate-100">
                        {{ card.value }}
                    </p>
                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">
                        {{ card.description }}
                    </p>
                    <svg
                        class="mt-6 h-14 w-full"
                        viewBox="0 0 180 56"
                        preserveAspectRatio="none"
                        aria-hidden="true"
                    >
                        <polyline
                            :points="sparklinePoints(visualSummary[index].points)"
                            fill="none"
                            :stroke="visualSummary[index].lineTone"
                            stroke-width="2.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </article>
            </section>

            <section class="grid gap-4 xl:grid-cols-3">
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-950 dark:text-slate-100">
                                Project Progress
                            </h2>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                Overall progress across all projects
                            </p>
                        </div>
                        <span class="rounded-md border border-slate-200 px-2 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:text-slate-300">
                            This Month
                        </span>
                    </div>

                    <div v-if="focusProject" class="mt-5">
                        <svg viewBox="0 0 240 140" class="h-44 w-full" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="dashboardProjectProgressFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.22" />
                                    <stop offset="100%" stop-color="#3b82f6" stop-opacity="0.03" />
                                </linearGradient>
                            </defs>
                            <line x1="0" y1="20" x2="240" y2="20" stroke="#e2e8f0" />
                            <line x1="0" y1="76" x2="240" y2="76" stroke="#e2e8f0" />
                            <line x1="0" y1="132" x2="240" y2="132" stroke="#e2e8f0" />
                            <path :d="projectAreaPath" fill="url(#dashboardProjectProgressFill)" />
                            <polyline
                                :points="projectChartPoints"
                                fill="none"
                                stroke="#3b82f6"
                                stroke-width="3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>

                        <div class="mt-2 grid grid-cols-4 text-xs text-slate-500 dark:text-slate-400">
                            <span>Jun 1</span>
                            <span>Jun 8</span>
                            <span>Jun 15</span>
                            <span class="text-right">Jun 29</span>
                        </div>
                    </div>

                    <div v-else class="mt-5 rounded-md border border-dashed border-slate-200 p-5 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        No projects are available yet.
                    </div>
                </article>

                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 19V5" />
                            <path d="M4 19h16" />
                            <path d="m7 15 4-4 3 3 5-7" />
                            <path d="M15 7h4v4" />
                        </svg>
                        <h2 class="text-sm font-semibold text-slate-950 dark:text-slate-100">
                            Latest Updates
                        </h2>
                    </div>

                    <div v-if="latestUpdates.length === 0" class="mt-5 rounded-md border border-dashed border-slate-200 p-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        No published updates yet.
                    </div>

                    <div v-else class="mt-5 space-y-4">
                        <article
                            v-for="update in latestUpdates"
                            :key="update.id"
                            class="rounded-md border border-slate-200 p-4 dark:border-slate-800"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-950 dark:text-slate-100">
                                        {{ update.title }}
                                    </h3>
                                    <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ update.project_title || 'Project' }} · {{ update.date || 'No date' }}
                                    </p>
                                </div>
                                <Link
                                    :href="update.show_url"
                                    class="shrink-0 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                >
                                    Open Project
                                </Link>
                            </div>
                        </article>
                    </div>
                </article>

                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center gap-2">
                        <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                            <path d="M14 2v6h6" />
                        </svg>
                        <h2 class="text-sm font-semibold text-slate-950 dark:text-slate-100">
                            Latest Files
                        </h2>
                    </div>

                    <div v-if="latestFiles.length === 0" class="mt-5 rounded-md border border-dashed border-slate-200 p-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        No available files yet.
                    </div>

                    <div v-else class="mt-5 space-y-4">
                        <article
                            v-for="file in latestFiles"
                            :key="file.id"
                            class="flex items-start justify-between gap-3 rounded-md border border-slate-200 p-4 dark:border-slate-800"
                        >
                            <div class="flex min-w-0 gap-3">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-600 dark:bg-blue-950 dark:text-blue-300">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                                        <path d="M14 2v6h6" />
                                        <path d="M8 13h8" />
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-semibold text-slate-950 dark:text-slate-100">
                                        {{ file.name }}
                                    </h3>
                                    <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ file.project_title || 'Project' }} · {{ file.date || 'No date' }}
                                    </p>
                                </div>
                            </div>
                            <a
                                :href="file.download_url"
                                class="shrink-0 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            >
                                Download
                            </a>
                        </article>
                    </div>
                </article>
            </section>

            <section>
                <div class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h2 class="text-lg font-semibold text-slate-950 dark:text-slate-100">
                            Recent Activity
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ summaryTotal }} tracked account items
                        </p>
                    </div>

                    <div v-if="recentActivity.length === 0" class="p-8 text-center">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            RA
                        </div>
                        <h3 class="mt-4 text-base font-semibold text-slate-950 dark:text-slate-100">
                            No recent activity yet
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">
                            Published updates, uploaded files, and support replies will appear here.
                        </p>
                    </div>

                    <div v-else>
                        <div class="hidden grid-cols-[1rem_7rem_minmax(14rem,1.4fr)_minmax(10rem,1fr)_13rem_5.5rem] items-center gap-6 border-b border-slate-200 bg-slate-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 md:grid dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400">
                            <span />
                            <span>Type</span>
                            <span>Activity</span>
                            <span>Project</span>
                            <span>Date</span>
                            <span>Action</span>
                        </div>

                        <div class="divide-y divide-slate-200 dark:divide-slate-800">
                            <article
                                v-for="item in recentActivity.slice(0, 5)"
                                :key="`${item.type}-${item.title}-${item.date}`"
                                class="grid gap-3 px-5 py-3 text-sm md:grid-cols-[1rem_7rem_minmax(14rem,1.4fr)_minmax(10rem,1fr)_13rem_5.5rem] md:items-center md:gap-6"
                            >
                                <span
                                    :class="[
                                        activityDotClass(item.type),
                                        'mt-1 h-2.5 w-2.5 shrink-0 rounded-full md:mt-0',
                                    ]"
                                />
                                <span class="w-fit rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300">
                                    {{ item.type }}
                                </span>
                                <h3 class="min-w-0 truncate font-semibold text-slate-950 dark:text-slate-100">
                                    {{ item.title }}
                                </h3>
                                <p class="min-w-0 truncate text-slate-500 dark:text-slate-400">
                                    {{ item.context || 'Client workspace' }}
                                </p>
                                <p class="text-left text-slate-500 dark:text-slate-400">
                                    {{ item.date || 'No date' }}
                                </p>
                                <Link
                                    :href="item.href"
                                    class="inline-flex w-fit items-center justify-center rounded-md border border-slate-200 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 hover:text-slate-950 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white"
                                >
                                    View
                                </Link>
                            </article>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
