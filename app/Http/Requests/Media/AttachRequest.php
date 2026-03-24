<?php

namespace App\Http\Requests\Media;

use Illuminate\Foundation\Http\FormRequest;

class AttachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_id' => ['required', 'integer', 'exists:media,id'],
            'tag'      => ['nullable', 'string', 'max:64'],
            'order'    => ['nullable', 'integer', 'min:0'],
        ];
    }
}
