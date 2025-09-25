<?php

namespace Modules\Employee\Database\Seeders;

use App\Models\User;
use Modules\Employee\Entities\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample users if they don't exist
        $users = [
            [
                'full_name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'password' => Hash::make('password'),
                'employee_id' => 'EMP001',
                'date_of_joined' => '2024-01-15',
            ],
            [
                'full_name' => 'Jane Smith',
                'email' => 'jane.smith@example.com',
                'password' => Hash::make('password'),
                'employee_id' => 'EMP002',
                'date_of_joined' => '2024-02-20',
            ],
            [
                'full_name' => 'Mike Johnson',
                'email' => 'mike.johnson@example.com',
                'password' => Hash::make('password'),
                'employee_id' => 'EMP003',
                'date_of_joined' => '2024-03-10',
            ],
        ];

        foreach ($users as $userData) {
            $employeeData = [
                'employee_id' => $userData['employee_id'],
                'date_of_joined' => $userData['date_of_joined'],
            ];
            unset($userData['employee_id'], $userData['date_of_joined']);

            // Create or find user
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            // Create employee record if it doesn't exist
            Employee::firstOrCreate(
                ['user_id' => $user->id],
                array_merge($employeeData, ['user_id' => $user->id])
            );
        }
    }
}