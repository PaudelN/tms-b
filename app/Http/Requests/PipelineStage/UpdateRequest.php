<?php

namespace App\Http\Requests\PipelineStage;

use App\Enums\PipelineStageStatus;
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
            'name'          => 'sometimes|required|string|min:2|max:255',
            'display_name'  => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:0',
            'status'        => ['sometimes', 'required', 'integer', Rule::enum(PipelineStageStatus::class)],
            'color'         => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'wip_limit'     => 'nullable|integer|min:1|max:9999',
            'extras'        => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'   => 'Stage name is required.',
            'name.min'        => 'Stage name must be at least 2 characters.',
            'name.max'        => 'Stage name cannot exceed 255 characters.',
            'status.required' => 'Please select a status.',
            'status.integer'  => 'Status must be an integer value.',
            'status.enum'     => 'The selected status is invalid.',
            'color.regex'     => 'Color must be a valid hex code (e.g. #3B82F6).',
            'wip_limit.min'   => 'WIP limit must be at least 1.',
            'wip_limit.max'   => 'WIP limit cannot exceed 9999.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
