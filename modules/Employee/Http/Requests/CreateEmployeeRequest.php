<?php

namespace Modules\Employee\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'employee_id' => 'required|string|unique:employees,employee_id',
            'date_of_joined' => 'required|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'The user ID is required.',
            'user_id.exists' => 'The selected user does not exist.',
            'employee_id.required' => 'The employee ID is required.',
            'employee_id.unique' => 'The employee ID has already been taken.',
            'date_of_joined.required' => 'The date of joining is required.',
            'date_of_joined.date' => 'The date of joining must be a valid date.',
        ];
    }
}
