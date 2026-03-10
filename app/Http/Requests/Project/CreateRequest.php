<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Helpers\ApiResponse;
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
            'name'        => 'required|string|min:3|max:255',
            'status'      => ['required', 'string', Rule::enum(ProjectStatus::class)],
            'visibility'  => ['required', 'string', Rule::enum(ProjectVisibility::class)],
            'description' => 'nullable|string|max:1000',
            'cover_image' => 'nullable|string|max:500',
            'start_date'  => 'nullable|date',
            'end_date'    => 'nullable|date|after_or_equal:start_date',
            'extra'       => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'Project name is required.',
            'name.min'            => 'Project name must be at least 3 characters.',
            'name.max'            => 'Project name cannot exceed 255 characters.',
            'status.required'     => 'Please select a status.',
            'status.enum'         => 'The selected status is invalid.',
            'visibility.required' => 'Please select a visibility setting.',
            'visibility.enum'     => 'The selected visibility is invalid.',
            'description.max'     => 'Description cannot exceed 1000 characters.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
