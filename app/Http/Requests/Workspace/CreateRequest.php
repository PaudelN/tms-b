<?php

namespace App\Http\Requests\Workspace;

use App\Enums\WorkspaceStatus;
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
            'name' => 'required|string|min:3|max:255',
            'status' => ['required', 'string', Rule::enum(WorkspaceStatus::class)],
            'description' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Workspace name is required.',
            'name.min' => 'Workspace name must be at least 3 characters.',
            'name.max' => 'Workspace name cannot exceed 255 characters.',
            'status.required' => 'Please select a status.',
            'status.enum' => 'The selected status is invalid.',
            'description.max' => 'Description cannot exceed 1000 characters.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
