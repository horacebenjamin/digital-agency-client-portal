<script setup>
import { computed, useAttrs } from 'vue';
import {
    AlertDialogContent,
    AlertDialogOverlay,
    AlertDialogPortal,
} from 'reka-ui';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
const delegatedAttrs = computed(() => {
    const { class: omittedClass, ...delegated } = attrs;

    return delegated;
});
</script>

<template>
    <AlertDialogPortal>
        <AlertDialogOverlay class="fixed inset-0 z-[70] bg-slate-950/45 backdrop-blur-[1px] data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0" />
        <AlertDialogContent
            v-bind="delegatedAttrs"
            :class="cn('fixed left-1/2 top-1/2 z-[71] grid w-[calc(100%-2rem)] max-w-sm -translate-x-1/2 -translate-y-1/2 gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl duration-200 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 dark:border-slate-800 dark:bg-slate-900', attrs.class)"
        >
            <slot />
        </AlertDialogContent>
    </AlertDialogPortal>
</template>
