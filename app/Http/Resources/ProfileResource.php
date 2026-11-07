<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * The signed-in user's own profile: the public view plus private fields.
 */
class ProfileResource extends UserResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'email' => $this->email,
        ];
    }
}
