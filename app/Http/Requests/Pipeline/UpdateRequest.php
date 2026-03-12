<?php

namespace App\Http\Requests\Pipeline;

use App\Enums\PipelineStatus;
use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'sometimes|required|string|min:3|max:255',
            'status'      => ['sometimes', 'required', 'integer', Rule::enum(PipelineStatus::class)],
            'description' => 'nullable|string|max:1000',
            'extras'      => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Pipeline name is required.',
            'name.min'        => 'Pipeline name must be at least 3 characters.',
            'name.max'        => 'Pipeline name cannot exceed 255 characters.',
            'status.required' => 'Please select a status.',
            'status.integer'  => 'Status must be an integer value.',
            'status.enum'     => 'The selected status is invalid.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
