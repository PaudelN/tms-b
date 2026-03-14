<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Helpers\ApiResponse;
use App\Models\PipelineStage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class CreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                  => 'required|string|min:1|max:255',
            'description'            => 'nullable|string|max:5000',
            'pipeline_stage_id'  => [
                'required',
                'integer',
                Rule::exists('pipeline_stages', 'id')->whereNull('deleted_at'),
            ],
            'priority'               => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'due_date'               => 'nullable|date|after_or_equal:today',
            'extra'                  => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'                 => 'Task title is required.',
            'title.max'                      => 'Task title cannot exceed 255 characters.',
            'pipeline_stage_id.required' => 'Please select a pipeline stage.',
            'pipeline_stage_id.exists'   => 'The selected stage does not exist.',
            'priority.enum'                  => 'Priority must be low, medium, high, or critical.',
            'due_date.after_or_equal'        => 'Due date cannot be in the past.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
