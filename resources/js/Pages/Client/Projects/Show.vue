<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const statusBadgeClasses = (status) => {
    return (
        {
            completed:
                'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
            in_progress:
                'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
            on_hold:
                'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
        }[status] ||
        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
    );
};

const updateBadgeClasses = (status) => {
    return (
        {
            published:
                'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
            draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
        }[status] ||
        'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200'
    );
};
</script>

<template>
    <Head :title="project.title" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <Link
                        :href="route('client.projects.index')"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        Back to My Projects
                    </Link>
                    <h2
                        class="mt-2 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ project.title }}
                    </h2>
                </div>
                <span
                    :class="[
                        statusBadgeClasses(project.status),
                        'w-fit rounded-full px-3 py-1 text-xs font-medium',
                    ]"
                >
                    {{ project.status_label }}
                </span>
            </div>
        </template>

        <div class="py-12">
            <div
                class="mx-auto grid max-w-7xl gap-6 px-4 sm:px-6 lg:grid-cols-3 lg:px-8"
            >
                <div class="space-y-6 lg:col-span-2">
                    <section
                        class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800"
                    >
                        <div class="flex items-center justify-between text-sm">
                            <span
                                class="font-medium text-gray-700 dark:text-gray-300"
                            >
                                Progress
                            </span>
                            <span
                                class="font-semibold text-gray-900 dark:text-gray-100"
                            >
                                {{ project.progress_percentage }}%
                            </span>
                        </div>
                        <div
                            class="mt-2 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                        >
                            <div
                                class="h-full rounded-full bg-indigo-600 dark:bg-indigo-400"
                                :style="{
                                    width: `${project.progress_percentage}%`,
                                }"
                            />
                        </div>

                        <div class="mt-8">
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                            >
                                Project Details
                            </h3>
                            <p
                                class="mt-3 whitespace-pre-line text-gray-600 dark:text-gray-400"
                            >
                                {{
                                    project.description ||
                                    'No description has been added for this project yet.'
                                }}
                            </p>
                        </div>
                    </section>

                    <section
                        class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Latest Updates
                        </h3>

                        <div
                            v-if="project.updates.length === 0"
                            class="mt-4 rounded-md border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400"
                        >
                            No project updates have been posted yet.
                        </div>

                        <div v-else class="mt-4 space-y-4">
                            <article
                                v-for="update in project.updates"
                                :key="update.id"
                                class="border-t border-gray-200 pt-4 first:border-t-0 first:pt-0 dark:border-gray-700"
                            >
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div>
                                        <h4
                                            class="font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            {{ update.title }}
                                        </h4>
                                        <p
                                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ update.created_date }}
                                        </p>
                                    </div>
                                    <span
                                        v-if="update.status_label"
                                        :class="[
                                            updateBadgeClasses(update.status),
                                            'w-fit rounded-full px-3 py-1 text-xs font-medium',
                                        ]"
                                    >
                                        {{ update.status_label }}
                                    </span>
                                </div>
                                <p
                                    class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-400"
                                >
                                    {{ update.summary || update.body }}
                                </p>
                            </article>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section
                        class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Project Files
                        </h3>

                        <div
                            v-if="project.files.length === 0"
                            class="mt-4 rounded-md border border-dashed border-gray-300 p-4 text-sm text-gray-600 dark:border-gray-700 dark:text-gray-400"
                        >
                            No files have been uploaded for this project yet.
                        </div>

                        <div v-else class="mt-4 space-y-4">
                            <article
                                v-for="file in project.files"
                                :key="file.id"
                                class="rounded-md border border-gray-200 p-4 dark:border-gray-700"
                            >
                                <div class="min-w-0">
                                    <h4
                                        class="truncate font-semibold text-gray-900 dark:text-gray-100"
                                    >
                                        {{ file.name }}
                                    </h4>
                                    <p
                                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ file.type || 'File' }} ·
                                        {{ file.uploaded_date }}
                                    </p>
                                </div>

                                <a
                                    :href="file.download_url"
                                    class="mt-4 inline-flex items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white"
                                >
                                    Download
                                </a>
                            </article>
                        </div>
                    </section>

                    <section
                        class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Overview
                        </h3>
                        <dl class="mt-4 space-y-4 text-sm">
                            <div>
                                <dt
                                    class="font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Priority
                                </dt>
                                <dd
                                    class="mt-1 text-gray-900 dark:text-gray-100"
                                >
                                    {{ project.priority }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Due Date
                                </dt>
                                <dd
                                    class="mt-1 text-gray-900 dark:text-gray-100"
                                >
                                    {{ project.due_date || 'Not set' }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Started
                                </dt>
                                <dd
                                    class="mt-1 text-gray-900 dark:text-gray-100"
                                >
                                    {{ project.started_at || 'Not started' }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Activity
                        </h3>
                        <dl class="mt-4 grid grid-cols-3 gap-4 text-center">
                            <div>
                                <dt
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Updates
                                </dt>
                                <dd
                                    class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ project.updates_count }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Files
                                </dt>
                                <dd
                                    class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ project.files_count }}
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Tickets
                                </dt>
                                <dd
                                    class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ project.support_tickets_count }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
