<?php

namespace Modules\Employee\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
        $employee = request()->route('employee');
        $employeeId = $employee ? $employee->id : null;

        return [
            'user_id' => 'sometimes|exists:users,id',
            'employee_id' => [
                'sometimes',
                'string',
                Rule::unique('employees', 'employee_id')->ignore($employeeId),
            ],
            'date_of_joined' => 'sometimes|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'The selected user does not exist.',
            'employee_id.unique' => 'The employee ID has already been taken.',
            'date_of_joined.date' => 'The date of joining must be a valid date.',
        ];
    }
}