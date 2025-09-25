<?php

namespace Modules\Employee\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Employee\Entities\EmployeeInfo;
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
        $employees = EmployeeInfo::with('user')->get();
        return response()->json($employees);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateEmployeeRequest $request): JsonResponse
    {
        $employee = EmployeeInfo::create($request->validated());
        $employee->load('user');
        
        return response()->json($employee, 201);
    }

    /**
     * Show the specified resource.
     */
    public function show(EmployeeInfo $employee): JsonResponse
    {
        $employee->load('user');
        return response()->json($employee);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, EmployeeInfo $employee): JsonResponse
    {
        $employee->update($request->validated());
        $employee->load('user');
        
        return response()->json($employee);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmployeeInfo $employee): JsonResponse
    {
        $employee->delete();
        return response()->json(['message' => 'Employee deleted successfully']);
    }
}
