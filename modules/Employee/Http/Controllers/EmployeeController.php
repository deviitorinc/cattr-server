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
        $employees = Employee::with('user')->paginate(15);
        
        $transformedData = [];
        foreach ($employees->items() as $employee) {
            $transformedData[] = [
                'id' => $employee->id,
                'name' => $employee->user->full_name,
                'email' => $employee->user->email,
                'employee_id' => $employee->employee_id,
                'date_of_joined' => $employee->date_of_joined->format('Y-m-d'),
                'user_id' => $employee->user_id,
                'created_at' => $employee->created_at,
                'updated_at' => $employee->updated_at,
            ];
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'data' => $transformedData,
            'pagination' => [
                'total' => $employees->total(),
                'perPage' => $employees->perPage(),
                'currentPage' => $employees->currentPage(),
                'lastPage' => $employees->lastPage(),
                'from' => $employees->firstItem(),
                'to' => $employees->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        
        // Check if there's a soft-deleted employee record for this user
        $existingEmployee = Employee::withTrashed()->where('user_id', $validatedData['user_id'])->first();
        
        if ($existingEmployee && $existingEmployee->trashed()) {
            // Restore and update the existing soft-deleted record
            $existingEmployee->restore();
            $existingEmployee->update([
                'employee_id' => $validatedData['employee_id'],
                'date_of_joined' => $validatedData['date_of_joined'],
            ]);
            $employee = $existingEmployee;
        } else {
            // Create a new employee record
            $employee = Employee::create($validatedData);
        }
        
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
        
        return response()->json([
            'data' => $employeeData
        ], 201);
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
        
        return response()->json([
            'data' => $employeeData
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee->update($request->validated());
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
        
        return response()->json([
            'data' => $employeeData
        ]);
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
        // Get users who don't have active employee records (excluding soft deleted ones)
        $users = User::whereDoesntHave('employee', function ($query) {
            $query->whereNull('deleted_at');
        })->get(['id', 'full_name', 'email']);
        
        return response()->json($users);
    }
}
