<?php

namespace App\Http\Requests\Media;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add policy check here when roles are introduced
    }

    public function rules(): array
    {
        $allowedMimes = implode(',', Media::ALLOWED_MIMES);
        $maxKb        = (int) (Media::MAX_SIZE / 1024); // validation rule expects KB

        return [
            'file' => [
                'required',
                'file',
                "mimes:{$allowedMimes}",
                "max:{$maxKb}",
            ],
            'alt' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.mimes'    => 'The uploaded file type is not allowed.',
            'file.max'      => 'The file may not be larger than ' . (Media::MAX_SIZE / 1024 / 1024) . ' MB.',
        ];
    }
}
