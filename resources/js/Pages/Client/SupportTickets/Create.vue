<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    projects: {
        type: Array,
        required: true,
    },
    priorities: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    project_id: props.projects[0]?.id || '',
    title: '',
    description: '',
    priority: 'medium',
});

const submit = () => {
    form.post(route('client.support-tickets.store'));
};
</script>

<template>
    <Head title="New Support Ticket" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route('client.support-tickets.index')"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    Back to Support Tickets
                </Link>
                <h2
                    class="mt-2 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    New Support Ticket
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form
                    class="space-y-6 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800"
                    @submit.prevent="submit"
                >
                    <div
                        v-if="projects.length === 0"
                        class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200"
                    >
                        A project must be assigned to your account before a
                        support ticket can be opened.
                    </div>

                    <div>
                        <InputLabel for="project_id" value="Project" />
                        <select
                            id="project_id"
                            v-model="form.project_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                            :disabled="projects.length === 0"
                            required
                        >
                            <option
                                v-for="project in projects"
                                :key="project.id"
                                :value="project.id"
                            >
                                {{ project.title }}
                            </option>
                        </select>
                        <InputError
                            class="mt-2"
                            :message="form.errors.project_id"
                        />
                    </div>

                    <div>
                        <InputLabel for="title" value="Subject" />
                        <TextInput
                            id="title"
                            v-model="form.title"
                            class="mt-1 block w-full"
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.title" />
                    </div>

                    <div>
                        <InputLabel for="priority" value="Priority" />
                        <select
                            id="priority"
                            v-model="form.priority"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                            required
                        >
                            <option
                                v-for="(label, value) in priorities"
                                :key="value"
                                :value="value"
                            >
                                {{ label }}
                            </option>
                        </select>
                        <InputError
                            class="mt-2"
                            :message="form.errors.priority"
                        />
                    </div>

                    <div>
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="8"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                            required
                        />
                        <InputError
                            class="mt-2"
                            :message="form.errors.description"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <Link
                            :href="route('client.support-tickets.index')"
                            class="rounded-md px-4 py-2 text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-gray-100"
                        >
                            Cancel
                        </Link>
                        <PrimaryButton
                            :disabled="form.processing || projects.length === 0"
                        >
                            Create Ticket
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
