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

export const createChatRequestBody = (messages) => ({
    messages: messages.map((message) => ({
        role: message.role,
        content: getMessageContent(message),
    })),
});

export const sendProjectChatMessage = (sendMessage, content) => {
    return sendMessage({ text: content });
};
