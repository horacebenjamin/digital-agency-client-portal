<script setup>
import { computed, useAttrs } from 'vue';
import { TooltipContent, TooltipPortal } from 'reka-ui';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const attrs = useAttrs();
const delegatedAttrs = computed(() => {
    const { class: omittedClass, ...delegated } = attrs;

    return delegated;
});
</script>

<template>
    <TooltipPortal>
        <TooltipContent
            v-bind="delegatedAttrs"
            :class="cn('z-[80] overflow-hidden rounded-md bg-slate-950 px-2.5 py-1.5 text-xs font-medium text-white shadow-md data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:animate-in data-[state=open]:fade-in-0 dark:bg-white dark:text-slate-950', attrs.class)"
        >
            <slot />
        </TooltipContent>
    </TooltipPortal>
</template>
