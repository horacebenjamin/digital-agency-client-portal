<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const aiSummary = ref(props.project.ai_summary || '');
const aiSummaryError = ref(props.project.ai_summary_error || '');
const aiSummaryStatus = ref(props.project.ai_summary_status || 'idle');
const aiSummaryLoading = computed(() => aiSummaryStatus.value === 'generating');
let aiSummaryPoll = null;

const stopAiSummaryPolling = () => {
    if (aiSummaryPoll) {
        clearInterval(aiSummaryPoll);
        aiSummaryPoll = null;
    }
};

const applyAiSummaryPayload = (payload) => {
    aiSummaryStatus.value = payload.status || 'idle';
    aiSummary.value = payload.summary || '';
    aiSummaryError.value = payload.message || '';

    if (aiSummaryStatus.value !== 'generating') {
        stopAiSummaryPolling();
    }
};

const pollAiSummary = async () => {
    try {
        const response = await window.axios.get(
            props.project.ai_summary_status_url,
        );

        applyAiSummaryPayload(response.data);
    } catch (error) {
        aiSummaryStatus.value = 'failed';
        aiSummaryError.value =
            error.response?.data?.message ||
            'The AI summary status could not be checked right now.';
        stopAiSummaryPolling();
    }
};

const startAiSummaryPolling = () => {
    if (aiSummaryPoll) {
        return;
    }

    aiSummaryPoll = setInterval(pollAiSummary, 3000);
};

const generateAiSummary = async () => {
    aiSummaryStatus.value = 'generating';
    aiSummaryError.value = '';

    try {
        const response = await window.axios.post(props.project.ai_summary_url);

        applyAiSummaryPayload(response.data);

        if (aiSummaryStatus.value === 'generating') {
            startAiSummaryPolling();
        }
    } catch (error) {
        aiSummaryStatus.value = 'failed';
        aiSummaryError.value =
            error.response?.data?.message ||
            'The AI summary could not be generated right now.';
    }
};

onMounted(() => {
    if (aiSummaryStatus.value === 'generating') {
        startAiSummaryPolling();
    }
});

onBeforeUnmount(stopAiSummaryPolling);

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
                            class="mt-4 rounded-md border border-dashed border-gray-300 bg-gray-50/60 p-5 dark:border-gray-700 dark:bg-gray-900/30"
                        >
                            <h4
                                class="font-semibold text-gray-900 dark:text-gray-100"
                            >
                                No updates posted yet
                            </h4>
                            <p
                                class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400"
                            >
                                Published milestones and progress notes from
                                your agency team will appear here.
                            </p>
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

                    <section
                        class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800"
                    >
                        <h3
                            class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                        >
                            Activity Timeline
                        </h3>

                        <div
                            v-if="project.timeline.length === 0"
                            class="mt-4 rounded-md border border-dashed border-gray-300 bg-gray-50/60 p-5 dark:border-gray-700 dark:bg-gray-900/30"
                        >
                            <h4
                                class="font-semibold text-gray-900 dark:text-gray-100"
                            >
                                No activity yet
                            </h4>
                            <p
                                class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400"
                            >
                                Project milestones, files, support activity,
                                and billing updates will appear here.
                            </p>
                        </div>

                        <ol v-else class="mt-5 space-y-5">
                            <li
                                v-for="item in project.timeline"
                                :key="`${item.type}-${item.occurred_at}-${item.description}`"
                                class="relative border-l border-gray-200 pl-5 dark:border-gray-700"
                            >
                                <span
                                    class="absolute -left-1.5 top-1.5 h-3 w-3 rounded-full bg-indigo-600 ring-4 ring-white dark:bg-indigo-400 dark:ring-gray-800"
                                />
                                <div
                                    class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            {{ item.label }}
                                        </p>
                                        <p
                                            class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400"
                                        >
                                            {{ item.description }}
                                        </p>
                                        <p
                                            v-if="item.actor"
                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            By {{ item.actor }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 flex-col gap-1 text-left sm:text-right"
                                    >
                                        <time
                                            class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                        >
                                            {{ item.occurred_at }}
                                        </time>
                                        <a
                                            v-if="item.url"
                                            :href="item.url"
                                            class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            {{ item.link_label || 'View' }}
                                        </a>
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section
                        class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between lg:flex-col"
                        >
                            <div>
                                <h3
                                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    AI Project Summary
                                </h3>
                                <p
                                    class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400"
                                >
                                    Generate a concise readout from recent
                                    project activity, tickets, files, and
                                    billing.
                                </p>
                            </div>

                            <button
                                type="button"
                                :disabled="aiSummaryLoading"
                                class="inline-flex w-fit items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70 dark:bg-indigo-500 dark:hover:bg-indigo-400"
                                @click="generateAiSummary"
                            >
                                {{
                                    aiSummaryLoading
                                        ? 'Generating...'
                                        : 'Generate Summary'
                                }}
                            </button>
                        </div>

                        <div
                            v-if="aiSummaryLoading"
                            class="mt-4 rounded-md border border-indigo-200 bg-indigo-50 p-4 text-sm leading-6 text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-200"
                        >
                            Your summary is being generated in the background.
                        </div>

                        <div
                            v-if="aiSummaryError"
                            class="mt-4 rounded-md border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-200"
                        >
                            {{ aiSummaryError }}
                        </div>

                        <p
                            v-if="aiSummary"
                            class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                        >
                            {{ aiSummary }}
                        </p>
                    </section>

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
                            class="mt-4 rounded-md border border-dashed border-gray-300 bg-gray-50/60 p-5 dark:border-gray-700 dark:bg-gray-900/30"
                        >
                            <h4
                                class="font-semibold text-gray-900 dark:text-gray-100"
                            >
                                No files uploaded yet
                            </h4>
                            <p
                                class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-400"
                            >
                                Shared documents, creative assets, and project
                                files will be available here once uploaded.
                            </p>
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
