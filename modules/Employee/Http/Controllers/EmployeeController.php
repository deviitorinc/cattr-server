<?php

namespace Modules\Employee\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Modules\Employee\Entities\Employee;
use Modules\Employee\Http\Requests\CreateEmployeeRequest;
use Modules\Employee\Http\Requests\UpdateEmployeeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $employees = Employee::with('user')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->user->full_name,
                    'email' => $employee->user->email,
                    'employee_id' => $employee->employee_id,
                    'date_of_joined' => $employee->date_of_joined->format('Y-m-d'),
                    'user_id' => $employee->user_id,
                    'created_at' => $employee->created_at,
                    'updated_at' => $employee->updated_at,
                ];
            });

        return response()->json(['data' => $employees]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());
        $employee->load('user');
        
        return response()->json($employee, 201);
    }

    /**
     * Show the specified resource.
     */
    public function show(Employee $employee): JsonResponse
    {
        $employee->load('user');
        
        $employeeData = [
            'id' => $employee->id,
            'name' => $employee->user->full_name,
            'email' => $employee->user->email,
            'employee_id' => $employee->employee_id,
            'date_of_joined' => $employee->date_of_joined->format('Y-m-d'),
            'user_id' => $employee->user_id,
            'created_at' => $employee->created_at,
            'updated_at' => $employee->updated_at,
        ];
        
        return response()->json($employeeData);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());
        $employee->load('user');
        
        return response()->json($employee);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();
        return response()->json(['message' => 'Employee deleted successfully']);
    }

    /**
     * Get available users for employee creation
     */
    public function getAvailableUsers(): JsonResponse
    {
        // Get users who don't already have employee records
        $users = User::whereDoesntHave('employee')->get(['id', 'full_name', 'email']);
        
        return response()->json($users);
    }
}
