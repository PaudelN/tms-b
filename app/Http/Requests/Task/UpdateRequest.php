<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
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
            'title'                  => 'sometimes|required|string|min:1|max:255',
            'description'            => 'nullable|string|max:5000',
            'pipeline_stage_id'  => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('pipeline_stages', 'id')->whereNull('deleted_at'),
            ],
            'priority'               => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'due_date'               => 'nullable|date',
            'extra'                  => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'               => 'Task title is required.',
            'title.max'                    => 'Task title cannot exceed 255 characters.',
            'pipeline_stage_id.exists' => 'The selected stage does not exist.',
            'priority.enum'                => 'Priority must be low, medium, high, or critical.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
