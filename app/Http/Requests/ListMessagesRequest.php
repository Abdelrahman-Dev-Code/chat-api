<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string for GET /api/messages: ?page=2&user_ids[]=1&user_ids[]=4
 */
class ListMessagesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'user_ids' => ['sometimes', 'array', 'max:50'],
            'user_ids.*' => ['integer', 'min:1'],
        ];
    }

    /**
     * The authors to filter by (empty means everyone).
     *
     * @return list<int>
     */
    public function userIds(): array
    {
        return array_values(array_unique(array_map(intval(...), $this->validated('user_ids', []))));
    }
}
