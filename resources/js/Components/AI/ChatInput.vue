<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { Button } from '@/Components/UI/ui/button';
import { Textarea } from '@/Components/UI/ui/textarea';
import { SendHorizontal } from 'lucide-vue-next';

const props = defineProps({
    isLoading: Boolean,
});

const emit = defineEmits(['send']);

const input = ref('');
const textareaRef = ref(null);

const handleSend = () => {
    const content = input.value.trim();

    if (!content || props.isLoading) {
        return;
    }

    emit('send', content);
    input.value = '';
    nextTick(resizeTextarea);
};

const handleKeydown = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        handleSend();
    }
};

const resizeTextarea = () => {
    const textarea = textareaRef.value?.$el;

    if (!textarea) {
        return;
    }

    textarea.style.height = 'auto';
    textarea.style.height = `${Math.min(textarea.scrollHeight, 160)}px`;
};

const clear = () => {
    input.value = '';
    nextTick(resizeTextarea);
};

onMounted(() => {
    textareaRef.value?.$el?.focus();
});

defineExpose({
    focus: () => textareaRef.value?.$el?.focus(),
    clear,
});
</script>

<template>
    <div class="w-full">
        <div class="relative flex items-end gap-2 rounded-2xl border border-slate-300 bg-white p-2 shadow-sm transition-all duration-200 ease-out focus-within:border-slate-400 focus-within:shadow-md focus-within:ring-2 focus-within:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-slate-600 dark:focus-within:ring-slate-800">
            <Textarea
                ref="textareaRef"
                v-model="input"
                placeholder="Ask anything about this project..."
                class="min-h-[52px] max-h-40 flex-1 resize-none overflow-y-auto border-0 bg-transparent px-3 py-3 text-sm leading-6 shadow-none focus-visible:ring-0"
                rows="2"
                aria-label="Message AI Project Assistant"
                @input="resizeTextarea"
                @keydown="handleKeydown"
            />
            <Button
                size="icon"
                class="mb-1 h-10 w-10 shrink-0 rounded-xl bg-slate-950 text-white shadow-sm transition duration-150 hover:bg-slate-800 hover:shadow-md focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-2 active:scale-95 disabled:bg-slate-200 disabled:text-slate-400 disabled:shadow-none dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200 dark:disabled:bg-slate-800 dark:disabled:text-slate-600"
                :disabled="!input.trim() || isLoading"
                @click="handleSend"
            >
                <SendHorizontal class="h-5 w-5" />
                <span class="sr-only">Send message</span>
            </Button>
        </div>
        <p class="mt-2 text-center text-[11px] text-slate-400 dark:text-slate-500">
            Press Enter to send · Shift+Enter for a new line
        </p>
    </div>
</template>
