<?php

namespace App\Http\Requests\PipelineStage;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stages'                   => 'required|array|min:1',
            'stages.*.id'              => 'required|integer|exists:pipeline_stages,id',
            'stages.*.display_order'   => 'required|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'stages.required'                  => 'Stages list is required.',
            'stages.min'                       => 'At least one stage must be provided.',
            'stages.*.id.required'             => 'Each stage must have an id.',
            'stages.*.id.exists'               => 'One or more stage IDs are invalid.',
            'stages.*.display_order.required'  => 'Each stage must have a display_order.',
            'stages.*.display_order.min'       => 'Display order cannot be negative.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validationError($validator->errors())
        );
    }
}
