<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body for creating or editing a message: { "content": "Hello" }
 *
 * Whitespace-only content is rejected because Laravel trims strings and
 * converts empty ones to null before validation.
 */
class SaveMessageRequest extends FormRequest
{
    public const int MAX_LENGTH = 2000;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function contentRules(): array
    {
        return ['required', 'string', 'max:'.self::MAX_LENGTH];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => self::contentRules(),
        ];
    }

    public function messageContent(): string
    {
        return $this->validated('content');
    }
}
