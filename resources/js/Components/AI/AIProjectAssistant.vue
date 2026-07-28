<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useChat } from '@ai-sdk/vue';
import { TextStreamChatTransport } from 'ai';
import { Button } from '@/Components/UI/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogTitle,
} from '@/Components/UI/ui/alert-dialog';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/UI/ui/tooltip';
import {
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerOverlay,
    DrawerPortal,
    DrawerRoot,
    DrawerTitle,
    DrawerTrigger,
} from 'reka-ui';
import { AlertCircle, Bot, MessageCircle, RefreshCw, SquarePen, X } from 'lucide-vue-next';
import ChatInput from './ChatInput.vue';
import ChatMessage from './ChatMessage.vue';
import ChatSuggestions from './ChatSuggestions.vue';
import TypingIndicator from './TypingIndicator.vue';
import {
    createChatRequestBody,
    sendProjectChatMessage,
} from './chatTransport.js';
import {
    resetChatSession,
    restoreNewChatTriggerFocus,
    shouldConfirmNewChat,
} from './chatReset.js';

const contextBadges = ['Progress', 'Files', 'Tickets', 'Timeline', 'Payments'];

const props = defineProps({
    project: {
        type: Object,
        required: true,
    },
});

const isOpen = ref(false);
const isNewChatDialogOpen = ref(false);
const isResettingChat = ref(false);
const focusComposerAfterDialog = ref(false);
const conversationRef = ref(null);
const chatInputRef = ref(null);
const newChatButtonRef = ref(null);
let scrollFrame = null;

const getCsrfToken = () => {
    const tokenCookie = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

    return tokenCookie ? decodeURIComponent(tokenCookie.split('=').slice(1).join('=')) : '';
};

const transport = new TextStreamChatTransport({
    api: route('client.projects.chat', props.project.id),
    credentials: 'same-origin',
    headers: () => ({
        Accept: 'text/plain',
        'X-XSRF-TOKEN': getCsrfToken(),
    }),
    prepareSendMessagesRequest: ({ messages: requestMessages }) => ({
        body: createChatRequestBody(requestMessages),
    }),
});

const {
    messages,
    sendMessage: sendChatMessage,
    status,
    error,
    regenerate,
    stop,
    clearError,
} = useChat({
    transport,
    messages: [],
    onError: (chatError) => {
        if (import.meta.env.DEV) {
            console.error('AI Project Assistant request failed:', chatError);
        }
    },
});

const isLoading = computed(() => status.value === 'submitted' || status.value === 'streaming');
const errorMessage = computed(() => {
    const technicalMessage = error.value?.message || '';

    if (/504|gateway time-out|network error/i.test(technicalMessage)) {
        return 'The AI provider took too long to respond. Please try again.';
    }

    return 'The AI service is currently unavailable. Please try again.';
});

const scrollToBottom = async () => {
    await nextTick();

    if (scrollFrame) {
        cancelAnimationFrame(scrollFrame);
    }

    scrollFrame = requestAnimationFrame(() => {
        if (!conversationRef.value) {
            return;
        }

        conversationRef.value.scrollTo({
            top: conversationRef.value.scrollHeight,
            behavior: 'smooth',
        });
    });
};

const handleOpenAutoFocus = (event) => {
    event.preventDefault();
    nextTick(() => chatInputRef.value?.focus());
};

const sendMessage = (content) => {
    return sendProjectChatMessage(sendChatMessage, content);
};

const focusNewChatButton = () => {
    const button = newChatButtonRef.value?.$el ?? newChatButtonRef.value;
    button?.focus?.();
};

const waitForStreamToSettle = () => {
    if (!isLoading.value) {
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const stopWatching = watch(status, (currentStatus) => {
            if (currentStatus === 'submitted' || currentStatus === 'streaming') {
                return;
            }

            stopWatching();
            resolve();
        }, { flush: 'sync' });
    });
};

const resetCurrentChat = async () => {
    if (isResettingChat.value) {
        return;
    }

    isResettingChat.value = true;

    try {
        await resetChatSession({
            isStreaming: () => isLoading.value,
            stopStream: stop,
            waitForStreamToSettle,
            clearMessages: () => {
                messages.value = [];
            },
            clearComposer: () => chatInputRef.value?.clear(),
            clearError,
            focusComposer: async () => {
                await nextTick();
                chatInputRef.value?.focus();
            },
        });
    } finally {
        isResettingChat.value = false;
        focusComposerAfterDialog.value = false;
    }
};

const requestNewChat = async () => {
    if (isResettingChat.value) {
        return;
    }

    if (shouldConfirmNewChat(messages.value)) {
        isNewChatDialogOpen.value = true;
        return;
    }

    await resetCurrentChat();
};

const confirmNewChat = () => {
    if (isResettingChat.value) {
        return;
    }

    focusComposerAfterDialog.value = true;
    void resetCurrentChat();
};

const handleNewChatDialogCloseAutoFocus = (event) => {
    event.preventDefault();

    if (focusComposerAfterDialog.value) {
        return;
    }

    nextTick(() => restoreNewChatTriggerFocus(focusNewChatButton));
};

watch(messages, scrollToBottom, { deep: true });
watch(isLoading, scrollToBottom);
watch(isOpen, (open) => {
    if (open) {
        scrollToBottom();
    }
});

onBeforeUnmount(() => {
    if (scrollFrame) {
        cancelAnimationFrame(scrollFrame);
    }
});
</script>

<template>
    <DrawerRoot v-model:open="isOpen" :modal="true" swipe-direction="down">
        <div class="fixed bottom-5 right-4 z-40 sm:bottom-6 sm:right-6">
            <div class="group relative">
                <span
                    id="ai-project-assistant-tooltip"
                    role="tooltip"
                    class="pointer-events-none absolute bottom-full right-0 mb-3 whitespace-nowrap rounded-lg bg-slate-950 px-3 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition duration-200 group-hover:-translate-y-0.5 group-hover:opacity-100 group-focus-within:-translate-y-0.5 group-focus-within:opacity-100 dark:bg-white dark:text-slate-950"
                >
                    AI Project Assistant
                </span>
                <DrawerTrigger
                    class="relative flex h-14 w-14 items-center justify-center rounded-full bg-slate-950 text-white shadow-[0_12px_35px_-8px_rgba(15,23,42,0.6)] ring-1 ring-white/10 transition duration-200 hover:-translate-y-1 hover:scale-105 hover:bg-slate-800 hover:shadow-[0_16px_40px_-8px_rgba(15,23,42,0.7)] focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 active:translate-y-0 active:scale-100 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-100"
                    aria-label="Open AI Project Assistant"
                    aria-describedby="ai-project-assistant-tooltip"
                >
                    <MessageCircle class="h-6 w-6" aria-hidden="true" />
                    <span class="absolute right-0.5 top-0.5 h-3 w-3 rounded-full border-2 border-white bg-emerald-500 dark:border-slate-950" aria-hidden="true" />
                </DrawerTrigger>
            </div>
        </div>

        <DrawerPortal>
            <DrawerOverlay class="fixed inset-0 z-50 bg-slate-950/45 backdrop-blur-[2px] duration-200 ease-out data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 motion-reduce:duration-0" />
            <DrawerContent
                class="fixed inset-x-0 bottom-0 z-50 mx-auto flex h-[calc(100dvh-0.75rem)] w-full max-w-[700px] flex-col overflow-hidden rounded-t-3xl border border-b-0 border-slate-200 bg-white shadow-[0_-24px_70px_-24px_rgba(15,23,42,0.45)] outline-none duration-300 ease-out data-[state=closed]:animate-out data-[state=closed]:slide-out-to-bottom-full data-[state=open]:animate-in data-[state=open]:slide-in-from-bottom-full motion-reduce:duration-0 sm:h-[min(700px,calc(100dvh-2rem))] dark:border-slate-800 dark:bg-slate-900"
                aria-describedby="ai-project-assistant-description"
                @open-auto-focus="handleOpenAutoFocus"
            >
                <div class="mx-auto mt-2 h-1.5 w-12 shrink-0 rounded-full bg-slate-300 sm:hidden dark:bg-slate-700" aria-hidden="true" />

                <header class="flex shrink-0 items-center gap-3 border-b border-slate-200 px-4 py-4 sm:px-6 dark:border-slate-800">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950">
                        <Bot class="h-5 w-5" aria-hidden="true" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <DrawerTitle class="truncate text-base font-semibold text-slate-950 dark:text-slate-100">
                            AI Project Assistant
                        </DrawerTitle>
                        <DrawerDescription id="ai-project-assistant-description" class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            Project context loaded <span aria-hidden="true">✓</span>
                            <span class="sr-only">and ready</span>
                        </DrawerDescription>
                    </div>
                    <TooltipProvider :delay-duration="250">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    ref="newChatButtonRef"
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="h-10 w-10 shrink-0 rounded-xl text-slate-500 transition duration-150 hover:bg-slate-100 hover:text-slate-950 focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-1 active:scale-95 disabled:opacity-50 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                                    :disabled="isResettingChat"
                                    aria-label="Start new chat"
                                    @click="requestNewChat"
                                >
                                    <SquarePen class="h-5 w-5" aria-hidden="true" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent side="bottom" :side-offset="6">
                                Start new chat
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                    <DrawerClose
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-950 focus:outline-none focus:ring-2 focus:ring-slate-400 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                        aria-label="Close AI Project Assistant"
                    >
                        <X class="h-5 w-5" aria-hidden="true" />
                    </DrawerClose>
                </header>

                <AlertDialog v-model:open="isNewChatDialogOpen">
                    <AlertDialogContent
                        @escape-key-down.stop
                        @close-auto-focus="handleNewChatDialogCloseAutoFocus"
                    >
                        <div class="space-y-2">
                            <AlertDialogTitle class="text-base font-semibold text-slate-950 dark:text-slate-100">
                                Start a new chat?
                            </AlertDialogTitle>
                            <AlertDialogDescription class="text-sm leading-6 text-slate-600 dark:text-slate-400">
                                This will clear the current conversation.
                            </AlertDialogDescription>
                        </div>
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <AlertDialogCancel as-child>
                                <Button type="button" variant="outline" :disabled="isResettingChat">
                                    Cancel
                                </Button>
                            </AlertDialogCancel>
                            <AlertDialogAction as-child>
                                <Button type="button" :disabled="isResettingChat" @click="confirmNewChat">
                                    Start new chat
                                </Button>
                            </AlertDialogAction>
                        </div>
                    </AlertDialogContent>
                </AlertDialog>

                <main
                    ref="conversationRef"
                    class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-slate-50/60 scroll-smooth dark:bg-slate-950/35"
                    aria-live="polite"
                    aria-label="AI conversation"
                >
                    <div v-if="messages.length === 0" class="px-4 py-8 sm:px-6 sm:py-10">
                        <div class="mx-auto w-full max-w-2xl text-center">
                            <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-xl bg-slate-950 text-white shadow-md shadow-slate-950/10 dark:bg-white dark:text-slate-950">
                                <Bot class="h-5 w-5" aria-hidden="true" />
                            </div>
                            <h2 class="mt-3 text-base font-semibold tracking-tight text-slate-950 dark:text-slate-100">
                                AI Project Assistant
                            </h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Hi! Ask me anything about this project.</p>

                            <ul class="mt-3 flex flex-wrap justify-center gap-1.5" aria-label="Available project context">
                                <li v-for="context in contextBadges" :key="context">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-200/70 px-2 py-1 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        <span class="text-emerald-600 dark:text-emerald-400" aria-hidden="true">✓</span>
                                        {{ context }}
                                    </span>
                                </li>
                            </ul>

                            <ChatSuggestions class="mt-4" @select="sendMessage" />
                        </div>
                    </div>

                    <div v-else class="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-6 sm:px-6">
                        <ChatMessage
                            v-for="message in messages"
                            :key="message.id"
                            :message="message"
                            :is-streaming="isLoading && message.role === 'assistant' && message.id === messages[messages.length - 1]?.id"
                        />
                        <TypingIndicator v-if="isLoading && messages[messages.length - 1]?.role === 'user'" />
                    </div>

                    <div v-if="error" class="mx-4 mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:mx-6 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300">
                        <AlertCircle class="mt-0.5 h-5 w-5 shrink-0" aria-hidden="true" />
                        <div class="flex-1">
                            <p class="font-semibold">Something went wrong</p>
                            <p class="mt-1">{{ errorMessage }}</p>
                            <button type="button" class="mt-2 inline-flex items-center gap-1.5 font-semibold hover:underline" @click="regenerate()">
                                <RefreshCw class="h-3.5 w-3.5" aria-hidden="true" />
                                Try again
                            </button>
                        </div>
                    </div>
                </main>

                <footer class="shrink-0 border-t border-slate-200 bg-white px-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 sm:px-5 sm:pb-4 dark:border-slate-800 dark:bg-slate-900">
                    <ChatInput ref="chatInputRef" :is-loading="isLoading" @send="sendMessage" />
                </footer>
            </DrawerContent>
        </DrawerPortal>
    </DrawerRoot>
</template>
