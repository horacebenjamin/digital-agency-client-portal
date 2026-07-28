export const shouldConfirmNewChat = (messages) => messages.length > 0;

export const restoreNewChatTriggerFocus = async (focusTrigger) => {
    await focusTrigger();
};

export const resetChatSession = async ({
    isStreaming,
    stopStream,
    waitForStreamToSettle,
    clearMessages,
    clearComposer,
    clearError,
    focusComposer,
}) => {
    if (isStreaming()) {
        await stopStream();
        await waitForStreamToSettle();
    }

    clearMessages();
    clearComposer();
    clearError();
    await focusComposer();
};
