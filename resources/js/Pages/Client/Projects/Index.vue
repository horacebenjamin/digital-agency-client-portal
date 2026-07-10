<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    projects: {
        type: Object,
        required: true,
    },
});

const projectStats = computed(() => {
    const items = props.projects.data || [];

    return [
        {
            label: 'Total Projects',
            value: items.length,
            description: 'Shown on this page',
        },
        {
            label: 'In Progress',
            value: items.filter((project) => project.status === 'in_progress').length,
            description: 'Currently moving forward',
        },
        {
            label: 'Overdue',
            value: items.filter((project) => project.is_overdue).length,
            description: 'Need schedule attention',
        },
    ];
});
</script>

<template>
    <Head title="My Projects" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-1">
                <h1 class="text-2xl font-bold text-slate-950 dark:text-slate-100">
                    My Projects
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Review progress, dates, and the latest agency updates.
                </p>
            </div>
        </template>

        <div class="space-y-6">
            <section class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="stat in projectStats"
                    :key="stat.label"
                    class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
                        {{ stat.label }}
                    </p>
                    <p class="mt-2 text-3xl font-bold text-slate-950 dark:text-slate-100">
                        {{ stat.value }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ stat.description }}
                    </p>
                </article>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-slate-100">
                        Project Portfolio
                    </h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Active and recent projects assigned to your client account.
                    </p>
                </div>

                <div v-if="projects.data.length === 0" class="p-8 text-center sm:p-10">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                        PR
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-950 dark:text-slate-100">
                        No assigned projects yet
                    </h3>
                    <p class="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        Projects assigned to your client account will appear here with status, progress, files, and updates.
                    </p>
                </div>

                <div v-else>
                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
                            <thead class="bg-slate-50 dark:bg-slate-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Project</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Progress</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Last Update</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Due Date</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                <tr
                                    v-for="project in projects.data"
                                    :key="project.id"
                                    class="transition hover:bg-slate-50 dark:hover:bg-slate-950"
                                >
                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-slate-950 dark:text-slate-100">
                                            {{ project.title }}
                                        </div>
                                        <div v-if="project.description" class="mt-1 max-w-sm truncate text-sm text-slate-500 dark:text-slate-400">
                                            {{ project.description }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span :class="[project.status_badge_classes, 'inline-flex rounded-full px-2.5 py-1 text-xs font-medium']">
                                            {{ project.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-2 w-32 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                                <div
                                                    class="h-full rounded-full bg-slate-900 dark:bg-slate-100"
                                                    :style="{ width: `${project.progress_percentage}%` }"
                                                />
                                            </div>
                                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">
                                                {{ project.progress_percentage }}%
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-300">
                                        {{ project.last_update || 'No updates yet' }}
                                    </td>
                                    <td class="px-5 py-4 text-sm">
                                        <span
                                            v-if="project.due_date"
                                            :class="project.is_overdue ? 'font-medium text-rose-600 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300'"
                                        >
                                            {{ project.due_date }}
                                        </span>
                                        <span v-else class="text-slate-500 dark:text-slate-400">
                                            No due date
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <Link
                                            :href="project.show_url"
                                            class="inline-flex items-center rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-slate-200 lg:hidden dark:divide-slate-800">
                        <article
                            v-for="project in projects.data"
                            :key="`card-${project.id}`"
                            class="p-5"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold text-slate-950 dark:text-slate-100">
                                        {{ project.title }}
                                    </h3>
                                    <p v-if="project.description" class="mt-1 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">
                                        {{ project.description }}
                                    </p>
                                </div>
                                <span :class="[project.status_badge_classes, 'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium']">
                                    {{ project.status_label }}
                                </span>
                            </div>
                            <div class="mt-4">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-500 dark:text-slate-400">Progress</span>
                                    <span class="font-medium text-slate-950 dark:text-slate-100">{{ project.progress_percentage }}%</span>
                                </div>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                                    <div class="h-full rounded-full bg-slate-900 dark:bg-slate-100" :style="{ width: `${project.progress_percentage}%` }" />
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm">
                                <span class="text-slate-500 dark:text-slate-400">
                                    Due: {{ project.due_date || 'No due date' }}
                                </span>
                                <Link :href="project.show_url" class="font-semibold text-slate-900 hover:text-slate-600 dark:text-slate-100 dark:hover:text-slate-300">
                                    View project
                                </Link>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <div v-if="projects.links.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in projects.links"
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
