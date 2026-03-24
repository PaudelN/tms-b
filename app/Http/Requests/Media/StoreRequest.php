<?php

namespace App\Http\Requests\Media;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:102400', // 100 MB max
            'name' => 'nullable|string|max:255',
            'extra' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.file' => 'The uploaded value must be a valid file.',
            'file.max' => 'File size cannot exceed 100 MB.',
            'name.max' => 'File name cannot exceed 255 characters.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
