<script setup>
import { computed } from 'vue';
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js';
import 'highlight.js/styles/github-dark.css';

const props = defineProps({
    content: {
        type: String,
        required: true,
    },
    isStreaming: {
        type: Boolean,
        default: false,
    },
});

// Configure marked with highlight.js
marked.setOptions({
    highlight: function (code, lang) {
        if (lang && hljs.getLanguage(lang)) {
            return hljs.highlight(code, { language: lang }).value;
        }
        return hljs.highlightAuto(code).value;
    },
    breaks: true,
    gfm: true,
});

const renderedHtml = computed(() => {
    const rawHtml = marked.parse(props.content || '');
    return DOMPurify.sanitize(rawHtml);
});
</script>

<template>
    <div
        :class="['markdown-content prose prose-slate max-w-none dark:prose-invert prose-sm', { 'markdown-content--streaming': isStreaming }]"
        v-html="renderedHtml"
    />
</template>

<style>
.markdown-content pre {
    @apply my-4 rounded-lg bg-slate-950 p-4 overflow-x-auto;
}

.markdown-content code {
    @apply rounded bg-slate-100 px-1 py-0.5 font-mono text-xs dark:bg-slate-800;
}

.markdown-content pre code {
    @apply bg-transparent p-0 text-slate-100;
}

.markdown-content p {
    @apply mb-4 last:mb-0;
}

.markdown-content ul, .markdown-content ol {
    @apply mb-4 ml-6 list-outside;
}

.markdown-content ul {
    @apply ml-0 list-none;
}

.markdown-content ol {
    @apply list-decimal;
}

.markdown-content h1, .markdown-content h2, .markdown-content h3, .markdown-content h4 {
    @apply mb-4 mt-6 font-bold first:mt-0;
}

.markdown-content a {
    @apply text-primary underline underline-offset-4;
}

.markdown-content blockquote {
    @apply border-l-4 border-slate-300 pl-4 italic dark:border-slate-700;
}

.markdown-content--streaming > :last-child::after {
    display: inline-block;
    width: 0.45rem;
    height: 1rem;
    margin-left: 0.2rem;
    vertical-align: -0.12rem;
    background: currentColor;
    content: '';
    animation: ai-streaming-cursor 0.8s steps(1, end) infinite;
}

@keyframes ai-streaming-cursor {
    0%, 45% {
        opacity: 1;
    }

    46%, 100% {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .markdown-content--streaming > :last-child::after {
        animation: none;
    }
}
</style>
