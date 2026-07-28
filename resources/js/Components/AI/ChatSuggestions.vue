<script setup>
import { suggestedPrompts } from './chatTransport.js';

defineEmits(['select']);
</script>

<template>
    <div class="flex flex-wrap justify-center gap-2">
        <button
            v-for="suggestion in suggestedPrompts"
            :key="suggestion"
            type="button"
            class="group inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 shadow-sm transition-all duration-200 ease-out hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 active:translate-y-0 active:scale-[0.98] motion-reduce:transform-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:bg-slate-800 dark:hover:text-white"
            @click="$emit('select', suggestion)"
        >
            <span>{{ suggestion }}</span>
            <span class="text-slate-400 transition-transform duration-200 ease-out group-hover:translate-x-0.5 motion-reduce:transform-none" aria-hidden="true">→</span>
        </button>
    </div>
</template>
