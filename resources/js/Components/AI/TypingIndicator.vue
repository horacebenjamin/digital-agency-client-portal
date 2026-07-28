<script setup>
import { Avatar, AvatarFallback } from '@/Components/UI/ui/avatar';
import { Bot } from 'lucide-vue-next';
</script>

<template>
    <div class="flex w-full items-start gap-3" role="status" aria-label="AI Assistant is responding">
        <Avatar class="h-8 w-8 shrink-0 border border-slate-200 dark:border-slate-700">
            <AvatarFallback class="bg-slate-950 text-white dark:bg-white dark:text-slate-950">
                <Bot class="h-4 w-4" aria-hidden="true" />
            </AvatarFallback>
        </Avatar>

        <div class="flex h-9 items-center gap-1.5 rounded-2xl rounded-bl-md bg-white px-4 shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:-0.3s]" />
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400 [animation-delay:-0.15s]" />
            <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-400" />
            <span class="sr-only">AI Assistant is responding</span>
        </div>
    </div>
</template>
