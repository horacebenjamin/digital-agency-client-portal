<script setup>
import { Avatar, AvatarFallback } from '@/Components/UI/ui/avatar';
import { Button } from '@/Components/UI/ui/button';
import { Bot, Check, Copy } from 'lucide-vue-next';
import MarkdownRenderer from './MarkdownRenderer.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
    isStreaming: {
        type: Boolean,
        default: false,
    },
});

const copied = ref(false);
const messageContent = computed(() => {
    if (typeof props.message.content === 'string') {
        return props.message.content;
    }

    return (props.message.parts || [])
        .filter((part) => part.type === 'text')
        .map((part) => part.text)
        .join('');
});

const copyToClipboard = async () => {
    try {
        await navigator.clipboard.writeText(messageContent.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
};

const isUser = props.message.role === 'user';
</script>

<template>
    <article :class="['flex w-full animate-in fade-in-0 slide-in-from-bottom-1 gap-3 duration-200 ease-out motion-reduce:animate-none', isUser ? 'justify-end' : 'justify-start']">
        <Avatar v-if="!isUser" class="mt-0.5 h-8 w-8 shrink-0 border border-slate-200 dark:border-slate-700">
            <AvatarFallback class="bg-slate-950 text-white dark:bg-white dark:text-slate-950">
                <Bot class="h-4 w-4" aria-hidden="true" />
            </AvatarFallback>
        </Avatar>

        <div :class="['min-w-0', isUser ? 'max-w-[85%] sm:max-w-[75%]' : 'max-w-[calc(100%-2.75rem)] flex-1']">
            <div :class="['text-sm leading-6', isUser ? 'rounded-2xl rounded-br-md bg-slate-950 px-4 py-3 text-white shadow-sm dark:bg-slate-100 dark:text-slate-950' : 'text-slate-700 dark:text-slate-200']">
                <p v-if="isUser" class="whitespace-pre-wrap break-words">{{ messageContent }}</p>
                <MarkdownRenderer v-else :content="messageContent" :is-streaming="isStreaming" />
            </div>

            <div v-if="!isUser" class="mt-1.5 flex items-center gap-2">
                <Button
                    variant="ghost"
                    size="icon"
                    class="h-7 w-7 rounded-md text-slate-400 transition duration-150 hover:bg-slate-200/70 hover:text-slate-700 focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-1 active:scale-95 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    @click="copyToClipboard"
                >
                    <Check v-if="copied" class="h-3 w-3" />
                    <Copy v-else class="h-3 w-3" />
                    <span class="sr-only">Copy message</span>
                </Button>
                <span v-if="copied" class="text-xs text-slate-500 dark:text-slate-400">Copied</span>
            </div>
        </div>
    </article>
</template>
