<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProjectChatRequest extends FormRequest
{
    public const MAX_MESSAGES = 50;

    public const MAX_USER_MESSAGE_LENGTH = 2000;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_id' => ['nullable', 'uuid'],
            'messages' => ['required', 'array', 'min:1', 'max:'.self::MAX_MESSAGES],
            'messages.*' => ['required', 'array:role,content'],
            'messages.*.content' => ['required', 'string'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
        ];
    }

    /**
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $messages = $this->input('messages');

                if (! is_array($messages) || $messages === []) {
                    return;
                }

                foreach ($messages as $index => $message) {
                    if (! is_array($message) || ($message['role'] ?? null) !== 'user') {
                        continue;
                    }

                    $content = $message['content'] ?? null;

                    if (is_string($content) && mb_strlen(trim($content)) > self::MAX_USER_MESSAGE_LENGTH) {
                        $validator->errors()->add(
                            "messages.{$index}.content",
                            'Messages must be 2,000 characters or fewer.',
                        );
                    }
                }

                $lastMessage = end($messages);

                if (! is_array($lastMessage) || ($lastMessage['role'] ?? null) !== 'user') {
                    $validator->errors()->add(
                        'messages',
                        'The final message must be from the user.',
                    );
                }
            },
        ];
    }
}
