import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import {
    cancelProjectChatRequest,
    createProjectChatContentStream,
    createChatRequestBody,
    getChatMessageValidationError,
    getProjectChatCompletionNotice,
    MAX_CHAT_MESSAGE_LENGTH,
    PROJECT_CHAT_STREAM_MARKER,
    PROJECT_CHAT_STREAM_PREFIX,
    ProjectChatTransport,
    runSingleSubmission,
    SAFE_CHAT_ERROR_MESSAGE,
    sendProjectChatMessage,
    suggestedPrompts,
    TRUNCATED_CHAT_MESSAGE,
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
const chatInputComponent = readFileSync(
    new URL('../../resources/js/Components/AI/ChatInput.vue', import.meta.url),
    'utf8',
);

const prompt = 'Summarise this project';

const encodedStream = (chunks) => {
    const encoder = new TextEncoder();

    return new ReadableStream({
        start(controller) {
            for (const chunk of chunks) {
                controller.enqueue(encoder.encode(chunk));
            }

            controller.close();
        },
    });
};

const readTextStream = async (stream, onChunk = () => {}) => {
    const reader = stream.pipeThrough(new TextDecoderStream()).getReader();
    let content = '';

    while (true) {
        const { done, value } = await reader.read();

        if (done) {
            return content;
        }

        content += value;
        onChunk(value);
    }
};

const finishRecord = (truncated, marker = PROJECT_CHAT_STREAM_MARKER) => {
    return `${marker}${JSON.stringify({
        type: 'finish',
        truncated,
    })}\n`;
};

const renderProjectChatStream = async (chunks, onStreamEvent = () => {}) => {
    const transport = new ProjectChatTransport({ onStreamEvent });
    const responseStream = transport.processResponseStream(encodedStream(chunks));
    let renderedText = '';

    for await (const part of responseStream) {
        if (part.type === 'text-delta') {
            renderedText += part.delta;
        }
    }

    return renderedText;
};

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
    const requestId = '123e4567-e89b-12d3-a456-426614174000';
    const requestBody = createChatRequestBody([
        {
            id: 'message-1',
            role: 'user',
            parts: [{ type: 'text', text: prompt }],
        },
    ], requestId);

    assert.deepEqual(requestBody, {
        request_id: requestId,
        messages: [{ role: 'user', content: prompt }],
    });
});

test('request cancellation sends only the active request identifier', async () => {
    const requests = [];
    const requestId = '123e4567-e89b-12d3-a456-426614174000';
    const cancelled = await cancelProjectChatRequest({
        api: '/projects/1/chat/cancel',
        csrfToken: 'csrf-token',
        requestId,
        fetcher: async (api, options) => {
            requests.push({ api, options });

            return { ok: true };
        },
    });

    assert.equal(cancelled, true);
    assert.equal(requests.length, 1);
    assert.equal(requests[0].api, '/projects/1/chat/cancel');
    assert.deepEqual(JSON.parse(requests[0].options.body), {
        request_id: requestId,
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

test('empty and whitespace-only messages cannot be submitted', async () => {
    let submissionCount = 0;
    const sendMessage = async () => {
        submissionCount += 1;
    };

    assert.equal(getChatMessageValidationError(''), 'Enter a message before sending.');
    assert.equal(getChatMessageValidationError(" \n\t "), 'Enter a message before sending.');
    assert.equal(await sendProjectChatMessage(sendMessage, ''), false);
    assert.equal(await sendProjectChatMessage(sendMessage, '   '), false);
    assert.equal(submissionCount, 0);
});

test('overlong messages are rejected with a clear validation message', async () => {
    const overlongMessage = 'a'.repeat(MAX_CHAT_MESSAGE_LENGTH + 1);
    let submitted = false;

    assert.equal(
        getChatMessageValidationError(overlongMessage),
        'Messages must be 2,000 characters or fewer.',
    );
    assert.equal(
        await sendProjectChatMessage(async () => {
            submitted = true;
        }, overlongMessage),
        false,
    );
    assert.equal(submitted, false);
    assert.match(chatInputComponent, /role="alert"/);
});

test('duplicate submissions are blocked while the first request is active', async () => {
    let busy = false;
    let submissionCount = 0;
    let releaseSubmission;
    const pendingSubmission = new Promise((resolve) => {
        releaseSubmission = resolve;
    });
    const options = {
        isBusy: () => busy,
        setBusy: (value) => {
            busy = value;
        },
        submit: async () => {
            submissionCount += 1;
            await pendingSubmission;
        },
    };

    const firstSubmission = runSingleSubmission(options);
    const duplicateSubmission = await runSingleSubmission(options);

    assert.equal(busy, true);
    assert.equal(duplicateSubmission, false);
    assert.equal(submissionCount, 1);

    releaseSubmission();
    assert.equal(await firstSubmission, true);
    assert.equal(busy, false);
});

test('send button is disabled during generation', () => {
    assert.match(
        chatInputComponent,
        /:disabled="[^"]*isLoading"/,
    );
    assert.match(chatInputComponent, /:aria-disabled="[^"]*isLoading"/);
});

test('loading clears after a successful response', async () => {
    let busy = false;

    await runSingleSubmission({
        isBusy: () => busy,
        setBusy: (value) => {
            busy = value;
        },
        submit: async () => {},
    });

    assert.equal(busy, false);
});

test('loading clears after provider failure, timeout, or interrupted stream', async () => {
    for (const failure of [
        new Error('provider internals'),
        new Error('request timed out'),
        new Error('stream interrupted'),
    ]) {
        let busy = false;

        await assert.rejects(
            runSingleSubmission({
                isBusy: () => busy,
                setBusy: (value) => {
                    busy = value;
                },
                submit: async () => {
                    throw failure;
                },
            }),
            failure,
        );

        assert.equal(busy, false);
    }
});

test('failed requests display a safe message without provider details', () => {
    assert.equal(
        SAFE_CHAT_ERROR_MESSAGE,
        'The assistant could not complete the response. Please try again.',
    );
    assert.match(
        assistantComponent,
        /const errorMessage = computed\(\(\) => SAFE_CHAT_ERROR_MESSAGE\)/,
    );
    assert.doesNotMatch(assistantComponent, /error\.value\?\.message/);
    assert.match(assistantComponent, /role="alert"[\s\S]*aria-live="assertive"/);
});

test('a complete finish record in one chunk is consumed as control data', async () => {
    const events = [];

    const renderedText = await renderProjectChatStream(
        [finishRecord(false)],
        (event) => events.push(event),
    );

    assert.equal(renderedText, '');
    assert.deepEqual(events, [{ type: 'finish', truncated: false }]);
});

test('a finish record split across chunks is buffered and consumed', async () => {
    const events = [];
    const terminalEvent = finishRecord(false);
    const chunks = [
        'Split-safe response',
        terminalEvent.slice(0, 2),
        terminalEvent.slice(2, 11),
        terminalEvent.slice(11, PROJECT_CHAT_STREAM_MARKER.length + 4),
        terminalEvent.slice(PROJECT_CHAT_STREAM_MARKER.length + 4),
    ];

    const renderedText = await renderProjectChatStream(
        chunks,
        (event) => events.push(event),
    );

    assert.equal(renderedText, 'Split-safe response');
    assert.deepEqual(events, [{ type: 'finish', truncated: false }]);
});

test('normal text and a finish record in the same chunk preserve only normal text', async () => {
    const renderedText = await renderProjectChatStream([
        `Normal response immediately before control data${finishRecord(false)}`,
    ]);

    assert.equal(renderedText, 'Normal response immediately before control data');
});

test('truncated true displays the length-limit notice', () => {
    const notice = getProjectChatCompletionNotice({
        type: 'finish',
        truncated: true,
    });

    assert.equal(notice, TRUNCATED_CHAT_MESSAGE);
    assert.equal(TRUNCATED_CHAT_MESSAGE, 'This response reached its length limit.');
    assert.match(assistantComponent, /completionNotice\.value = getProjectChatCompletionNotice\(event\)/);
    assert.match(assistantComponent, /v-if="completionNotice"/);
    assert.match(assistantComponent, /role="status"[\s\S]*aria-live="polite"/);
});

test('truncated false does not display a warning', () => {
    const notice = getProjectChatCompletionNotice({
        type: 'finish',
        truncated: false,
    });

    assert.equal(notice, '');
});

test('no internal control text appears in rendered assistant content', async () => {
    const normalizedMarker = `\n${PROJECT_CHAT_STREAM_PREFIX}`;
    const renderedText = await renderProjectChatStream([
        `Visible assistant content${finishRecord(false, normalizedMarker)}`,
    ]);

    assert.equal(renderedText, 'Visible assistant content');
    assert.doesNotMatch(renderedText, /AI_PROJECT_ASSISTANT_EVENT/);
    assert.doesNotMatch(renderedText, /"type":"finish"/);
    assert.doesNotMatch(renderedText, /"truncated":false/);
});

test('truncated response retains partial content and emits the length-limit state', async () => {
    const events = [];
    const terminalEvent = finishRecord(true);
    const stream = createProjectChatContentStream(
        encodedStream([
            'Partial response',
            terminalEvent.slice(0, PROJECT_CHAT_STREAM_MARKER.length + 5),
            terminalEvent.slice(PROJECT_CHAT_STREAM_MARKER.length + 5),
        ]),
        (event) => events.push(event),
    );

    assert.equal(await readTextStream(stream), 'Partial response');
    assert.deepEqual(events, [{ type: 'finish', truncated: true }]);
});

test('unexpected stream termination keeps partial content and reports failure', async () => {
    let partialContent = '';
    const stream = createProjectChatContentStream(
        encodedStream(['Partial response']),
    );

    await assert.rejects(
        readTextStream(stream, (chunk) => {
            partialContent += chunk;
        }),
        new RegExp(SAFE_CHAT_ERROR_MESSAGE.replaceAll('.', '\\.')),
    );
    assert.equal(partialContent, 'Partial response');
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
    assert.match(assistantComponent, /This will clear the current session conversation\./);
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
        cancelServerRequest: async () => {
            events.push('release-server-lock');
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

    assert.deepEqual(events, ['abort', 'release-server-lock', 'stream-settled', 'clear-messages']);
    assert.deepEqual(messages, []);
    assert.equal(composer, '');
    assert.equal(error, undefined);
    assert.equal(composerFocused, true);
    assert.match(assistantComponent, /v-if="messages\.length === 0"[\s\S]*<ChatSuggestions/);
});

test('an old stream cannot update the UI after New Chat has cleared the session', async () => {
    const messages = [{ id: 'user-message' }];
    let streamCanWrite = true;

    await resetChatSession({
        isStreaming: () => true,
        stopStream: async () => {
            streamCanWrite = false;
        },
        waitForStreamToSettle: async () => {},
        clearMessages: () => {
            messages.splice(0);
        },
        clearComposer: () => {},
        clearError: () => {},
        focusComposer: async () => {},
    });

    if (streamCanWrite) {
        messages.push({ id: 'stale-assistant-message' });
    }

    assert.deepEqual(messages, []);
});

test('New Chat cancellation releases the request before allowing an immediate submission', async () => {
    let serverLockHeld = true;
    let busy = false;
    let submissionCount = 0;

    await resetChatSession({
        isStreaming: () => true,
        stopStream: async () => {},
        cancelServerRequest: async () => {
            serverLockHeld = false;
        },
        waitForStreamToSettle: async () => {},
        clearMessages: () => {},
        clearComposer: () => {},
        clearError: () => {},
        focusComposer: async () => {},
    });

    await runSingleSubmission({
        isBusy: () => busy,
        setBusy: (value) => {
            busy = value;
        },
        submit: async () => {
            assert.equal(serverLockHeld, false);
            submissionCount += 1;
        },
    });

    assert.equal(submissionCount, 1);
});

test('closing the panel stops an active response and clears local loading state', () => {
    assert.match(
        assistantComponent,
        /watch\(isOpen,[\s\S]*void stopActiveResponse\(\)/,
    );
    assert.match(
        assistantComponent,
        /const stopActiveResponse = async \(\) =>[\s\S]*cancelActiveServerRequest\(requestId\)[\s\S]*isSubmitting\.value = false/,
    );
});

test('panel close cancellation uses the same owner-scoped server release path', () => {
    assert.match(
        assistantComponent,
        /watch\(isOpen,[\s\S]*void stopActiveResponse\(\)/,
    );
    assert.match(
        assistantComponent,
        /Promise\.all\(\[[\s\S]*stop\(\),[\s\S]*cancelActiveServerRequest\(requestId\)/,
    );
});

test('Enter sends while Shift+Enter continues to create a new line', () => {
    assert.match(
        chatInputComponent,
        /if \(e\.key === 'Enter' && !e\.shiftKey\) \{[\s\S]*e\.preventDefault\(\);[\s\S]*handleSend\(\);/,
    );
    assert.doesNotMatch(chatInputComponent, /e\.key === 'Enter' && e\.shiftKey/);
});

test('automatic scrolling respects intentional upward scrolling', () => {
    assert.match(assistantComponent, /distanceFromBottom <= 96/);
    assert.match(
        assistantComponent,
        /if \(!force && !shouldAutoScroll\.value\) \{[\s\S]*return;/,
    );
    assert.match(assistantComponent, /@scroll\.passive="handleConversationScroll"/);
});
