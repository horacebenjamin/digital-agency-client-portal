import { TextStreamChatTransport } from 'ai';

export const MAX_CHAT_MESSAGE_LENGTH = 2000;
export const SAFE_CHAT_ERROR_MESSAGE = 'The assistant could not complete the response. Please try again.';
export const TRUNCATED_CHAT_MESSAGE = 'This response reached its length limit.';
export const PROJECT_CHAT_STREAM_PREFIX = 'AI_PROJECT_ASSISTANT_EVENT:';
export const PROJECT_CHAT_STREAM_MARKER = `\n\u001e${PROJECT_CHAT_STREAM_PREFIX}`;

export const suggestedPrompts = [
    'Summarise this project',
    'Which support tickets are still open?',
    'What files have been uploaded?',
    'Draft a client update',
    'What payment requests are outstanding?',
    'What should happen next?',
];

export const getMessageContent = (message) => {
    if (typeof message.content === 'string') {
        return message.content;
    }

    return (message.parts || [])
        .filter((part) => part.type === 'text')
        .map((part) => part.text)
        .join('');
};

export const createChatRequestBody = (messages, requestId) => ({
    request_id: requestId,
    messages: messages.map((message) => ({
        role: message.role,
        content: getMessageContent(message),
    })),
});

export const getChatMessageValidationError = (content) => {
    if (typeof content !== 'string' || !content.trim()) {
        return 'Enter a message before sending.';
    }

    if ([...content.trim()].length > MAX_CHAT_MESSAGE_LENGTH) {
        return 'Messages must be 2,000 characters or fewer.';
    }

    return null;
};

export const sendProjectChatMessage = (sendMessage, content) => {
    const validationError = getChatMessageValidationError(content);

    if (validationError) {
        return Promise.resolve(false);
    }

    return sendMessage({ text: content.trim() });
};

export const cancelProjectChatRequest = async ({
    api,
    csrfToken,
    requestId,
    fetcher = globalThis.fetch,
}) => {
    if (!requestId) {
        return false;
    }

    try {
        const response = await fetcher(api, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                request_id: requestId,
            }),
        });

        return response.ok;
    } catch {
        return false;
    }
};

export const runSingleSubmission = async ({
    isBusy,
    setBusy,
    submit,
}) => {
    if (isBusy()) {
        return false;
    }

    setBusy(true);

    try {
        await submit();

        return true;
    } finally {
        setBusy(false);
    }
};

export const getProjectChatCompletionNotice = (event) => {
    return event.type === 'finish' && event.truncated === true
        ? TRUNCATED_CHAT_MESSAGE
        : '';
};

export const createProjectChatContentStream = (stream, onStreamEvent = () => {}) => {
    const decoder = new TextDecoder();
    const encoder = new TextEncoder();
    let buffer = '';
    let receivedTerminalEvent = false;

    const emitText = (controller, text) => {
        if (text) {
            controller.enqueue(encoder.encode(text));
        }
    };

    const processBuffer = (controller, isFinal = false) => {
        if (receivedTerminalEvent) {
            buffer = '';

            return;
        }

        const markerPosition = buffer.indexOf(PROJECT_CHAT_STREAM_PREFIX);

        if (markerPosition >= 0) {
            let normalText = buffer.slice(0, markerPosition);

            if (normalText.endsWith('\n\u001e')) {
                normalText = normalText.slice(0, -2);
            } else if (normalText.endsWith('\n') || normalText.endsWith('\u001e')) {
                normalText = normalText.slice(0, -1);
            }

            emitText(controller, normalText);
            buffer = buffer.slice(markerPosition);
            const eventPayloadStart = PROJECT_CHAT_STREAM_PREFIX.length;
            const eventPayloadEnd = buffer.indexOf('\n', eventPayloadStart);

            if (eventPayloadEnd < 0 && !isFinal) {
                return;
            }

            const eventPayload = buffer
                .slice(eventPayloadStart, eventPayloadEnd < 0 ? undefined : eventPayloadEnd)
                .trim();
            let event;

            try {
                event = JSON.parse(eventPayload);
            } catch {
                throw new Error(SAFE_CHAT_ERROR_MESSAGE);
            }

            if (!event || typeof event !== 'object' || Array.isArray(event)) {
                throw new Error(SAFE_CHAT_ERROR_MESSAGE);
            }

            const sanitizedEvent = event.type === 'finish'
                ? {
                    type: 'finish',
                    truncated: event.truncated === true,
                }
                : {
                    type: event.type,
                };

            receivedTerminalEvent = true;
            buffer = '';
            onStreamEvent(sanitizedEvent);

            if (sanitizedEvent.type === 'error') {
                throw new Error(SAFE_CHAT_ERROR_MESSAGE);
            }

            if (sanitizedEvent.type !== 'finish') {
                throw new Error(SAFE_CHAT_ERROR_MESSAGE);
            }

            return;
        }

        if (isFinal) {
            emitText(controller, buffer);
            buffer = '';

            if (!receivedTerminalEvent) {
                throw new Error(SAFE_CHAT_ERROR_MESSAGE);
            }

            return;
        }

        const retainedLength = PROJECT_CHAT_STREAM_PREFIX.length + 1;

        if (buffer.length > retainedLength) {
            const emitLength = buffer.length - retainedLength;
            emitText(controller, buffer.slice(0, emitLength));
            buffer = buffer.slice(emitLength);
        }
    };

    return stream.pipeThrough(new TransformStream({
        transform(chunk, controller) {
            if (receivedTerminalEvent) {
                return;
            }

            buffer += decoder.decode(chunk, { stream: true });
            processBuffer(controller);
        },
        flush(controller) {
            buffer += decoder.decode();
            processBuffer(controller, true);
        },
    }));
};

export class ProjectChatTransport extends TextStreamChatTransport {
    constructor({ onStreamEvent, ...options } = {}) {
        super(options);
        this.onStreamEvent = onStreamEvent;
    }

    processResponseStream(stream) {
        return super.processResponseStream(
            createProjectChatContentStream(stream, this.onStreamEvent),
        );
    }
}
