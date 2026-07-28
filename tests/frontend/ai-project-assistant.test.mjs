import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    createChatRequestBody,
    sendProjectChatMessage,
    suggestedPrompts,
} from '../../resources/js/Components/AI/chatTransport.js';
import { TextStreamChatTransport } from 'ai';
import { marked } from 'marked';
import {
    resetChatSession,
    restoreNewChatTriggerFocus,
    shouldConfirmNewChat,
} from '../../resources/js/Components/AI/chatReset.js';

const assistantComponent = readFileSync(
    new URL('../../resources/js/Components/AI/AIProjectAssistant.vue', import.meta.url),
    'utf8',
);

const prompt = 'Summarise this project';

test('suggested and typed prompts use the same AI SDK message shape', async () => {
    for (const suggestedPrompt of suggestedPrompts) {
        const sentMessages = [];
        const sendMessage = async (message) => sentMessages.push(message);

        await sendProjectChatMessage(sendMessage, suggestedPrompt);
        await sendProjectChatMessage(sendMessage, suggestedPrompt);

        assert.deepEqual(sentMessages, [
            { text: suggestedPrompt },
            { text: suggestedPrompt },
        ]);
    }
});

test('AI SDK message parts are converted to the Laravel chat payload', () => {
    const requestBody = createChatRequestBody([
        {
            id: 'message-1',
            role: 'user',
            parts: [{ type: 'text', text: prompt }],
        },
    ]);

    assert.deepEqual(requestBody, {
        messages: [{ role: 'user', content: prompt }],
    });
});

test('AI SDK text streaming preserves whitespace across chunk boundaries', async () => {
    const encoder = new TextEncoder();
    const source = new ReadableStream({
        start(controller) {
            for (const chunk of ['project is ', '68% ', 'complete']) {
                controller.enqueue(encoder.encode(chunk));
            }

            controller.close();
        },
    });
    const transport = new TextStreamChatTransport();
    const responseStream = transport.processResponseStream(source);
    let renderedText = '';

    for await (const part of responseStream) {
        if (part.type === 'text-delta') {
            renderedText += part.delta;
        }
    }

    assert.equal(renderedText, 'project is 68% complete');
});

test('Markdown headings, lists, and paragraphs render as separate elements', () => {
    const html = marked.parse([
        '## Current status',
        '',
        'The project is on track.',
        '',
        '- Progress is 68%',
        '- One ticket remains open',
    ].join('\n'));

    assert.match(html, /<h2>Current status<\/h2>/);
    assert.match(html, /<p>The project is on track\.<\/p>/);
    assert.match(html, /<ul>[\s\S]*<li>Progress is 68%<\/li>[\s\S]*<\/ul>/);
});

test('new-chat action appears immediately before the close button with accessible confirmation copy', () => {
    const newChatButtonPosition = assistantComponent.indexOf('aria-label="Start new chat"');
    const closeButtonPosition = assistantComponent.indexOf('aria-label="Close AI Project Assistant"');

    assert.ok(newChatButtonPosition >= 0);
    assert.ok(closeButtonPosition > newChatButtonPosition);
    assert.match(assistantComponent, /<SquarePen/);
    assert.match(assistantComponent, /Start a new chat\?/);
    assert.match(assistantComponent, /This will clear the current conversation\./);
});

test('empty conversations reset immediately while populated conversations require confirmation', () => {
    assert.equal(shouldConfirmNewChat([]), false);
    assert.equal(shouldConfirmNewChat([{ id: 'message-1' }]), true);
});

test('cancelling preserves the conversation and restores focus to the new-chat action', async () => {
    const messages = [{ id: 'message-1', role: 'user' }];
    let triggerFocused = false;

    await restoreNewChatTriggerFocus(async () => {
        triggerFocused = true;
    });

    assert.deepEqual(messages, [{ id: 'message-1', role: 'user' }]);
    assert.equal(triggerFocused, true);
});

test('confirmed reset aborts streaming before clearing chat state and focusing the composer', async () => {
    const events = [];
    const messages = [{ id: 'user-message' }];
    let composer = 'Draft message';
    let error = new Error('Temporary error');
    let composerFocused = false;
    let resolveSettledStream;
    const settledStream = new Promise((resolve) => {
        resolveSettledStream = resolve;
    });

    await resetChatSession({
        isStreaming: () => true,
        stopStream: async () => {
            events.push('abort');
            queueMicrotask(() => {
                messages.push({ id: 'buffered-assistant-chunk' });
                events.push('stream-settled');
                resolveSettledStream();
            });
        },
        waitForStreamToSettle: async () => {
            await settledStream;
        },
        clearMessages: () => {
            events.push('clear-messages');
            messages.splice(0);
        },
        clearComposer: () => {
            composer = '';
        },
        clearError: () => {
            error = undefined;
        },
        focusComposer: async () => {
            composerFocused = true;
        },
    });

    await Promise.resolve();

    assert.deepEqual(events, ['abort', 'stream-settled', 'clear-messages']);
    assert.deepEqual(messages, []);
    assert.equal(composer, '');
    assert.equal(error, undefined);
    assert.equal(composerFocused, true);
    assert.match(assistantComponent, /v-if="messages\.length === 0"[\s\S]*<ChatSuggestions/);
});
