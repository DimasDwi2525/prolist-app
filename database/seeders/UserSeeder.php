<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Department;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $marketingDepartmentId = Department::where('name', 'MARKETING')->value('id');
        $engineeringDepartmentId = Department::where('name', 'ENGINEERING')->value('id');
        $superAdminRoleId = Role::where('name', 'super_admin')->where('type_role', 1)->value('id');
        $supervisorMarketingRoleId = Role::where('name', 'supervisor marketing')->where('type_role', 1)->value('id');
        $engineerRoleId = Role::where('name', 'engineer')->where('type_role', 1)->value('id');
        $projectManagerRoleId = Role::where('name', 'project manager')->where('type_role', 1)->value('id');

        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'admin',
                'password' => bcrypt('password'),
                'role_id' => $superAdminRoleId,
                'department_id' => $marketingDepartmentId,
                'pin' => 727813
            ]
        );

        User::updateOrCreate(
            ['email' => 'marketing@example.com'],
            [
                'name' => 'marketing',
                'password' => bcrypt('password'),
                'role_id' => $supervisorMarketingRoleId,
                'department_id' => $marketingDepartmentId,
                'pin' => 727813
            ]
        );

        User::updateOrCreate(
            ['email' => 'engineer@example.com'],
            [
                'name' => 'engineer',
                'password' => bcrypt('password'),
                'role_id' => $engineerRoleId,
                'department_id' => $engineeringDepartmentId,
                'pin' => 727813
            ]
        );

        User::updateOrCreate(
            ['email' => 'project.manager@example.com'],
            [
                'name' => 'project manager',
                'password' => bcrypt('password'),
                'role_id' => $projectManagerRoleId,
                'department_id' => $engineeringDepartmentId,
                'pin' => 727813
            ]
        );
    }
}
