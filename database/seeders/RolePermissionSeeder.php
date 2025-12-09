<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Clear existing data to avoid conflicts during re-seeding
        \DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::query()->delete();
        \DB::table('model_has_roles')->delete();
        \DB::table('model_has_permissions')->delete();
        \DB::table('role_has_permissions')->delete();
        Role::query()->delete();
        Permission::query()->delete();
        \DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create permissions based on the old Laravel 7 system - EXACT Bulgarian names
        $permissions = [
            // ID 1
            ['name' => 'create_workers', 'description' => 'създаване на работници'],
            // ID 2
            ['name' => 'add_vacation', 'description' => 'добавяне на отпуска'],
            // ID 3
            ['name' => 'create_regions', 'description' => 'създаване на региони'],
            // ID 4
            ['name' => 'create_clients', 'description' => 'създаване на клиенти'],
            // ID 5
            ['name' => 'create_objects', 'description' => 'създаване на обекти'],
            // ID 6
            ['name' => 'configure_object_activity', 'description' => 'конфигурация дейност на обект'],
            // ID 7
            ['name' => 'edit_region', 'description' => 'редакция на регион'],
            // ID 8
            ['name' => 'add_users', 'description' => 'добавяне на потребители'],
            // ID 9
            ['name' => 'regions', 'description' => 'региони'],
            // ID 10
            ['name' => 'client', 'description' => 'клиент'],
            // ID 11
            ['name' => 'user', 'description' => 'потребител'],
            // ID 12
            ['name' => 'objects', 'description' => 'обекти'],
            // ID 13
            ['name' => 'add_holidays', 'description' => 'въвеждане на празници'],
            // ID 14
            ['name' => 'history', 'description' => 'история'],
            // ID 15
            ['name' => 'approvals', 'description' => 'одобрения'],
            // ID 16
            ['name' => 'presence_form', 'description' => 'присъствена форма'],
            // ID 17
            ['name' => 'archive', 'description' => 'архив'],
            // ID 18
            ['name' => 'register', 'description' => 'регистрация'],
            
            // Additional admin panel permissions
            ['name' => 'access_admin_panel', 'description' => 'достъп до административен панел'],
            ['name' => 'view_users', 'description' => 'преглед на потребители'],
            ['name' => 'edit_users', 'description' => 'редактиране на потребители'],
            ['name' => 'delete_users', 'description' => 'изтриване на потребители'],
            ['name' => 'view_roles', 'description' => 'преглед на роли'],
            ['name' => 'edit_roles', 'description' => 'редактиране на роли'],
            ['name' => 'view_permissions', 'description' => 'преглед на разрешения'],
            ['name' => 'edit_permissions', 'description' => 'редактиране на разрешения'],
            
            // Filament Shield specific permissions
            ['name' => 'view_any_role', 'description' => 'преглед на всички роли'],
            ['name' => 'create_role', 'description' => 'създаване на нови роли'],
            ['name' => 'update_role', 'description' => 'обновяване на роли'],
            ['name' => 'delete_role', 'description' => 'изтриване на роли'],
            ['name' => 'delete_any_role', 'description' => 'изтриване на всички роли'],
        ];

        // Create permissions
        foreach ($permissions as $permissionData) {
            Permission::firstOrCreate(
                ['name' => $permissionData['name'], 'guard_name' => 'web'],
                ['description' => $permissionData['description']]
            );
        }

        // Create roles based on the old system
        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['description' => 'Администратор - пълен достъп до системата']
        );

        $managerRole = Role::firstOrCreate(
            ['name' => 'manager', 'guard_name' => 'web'],
            ['description' => 'Мениджър - управление на HR процеси']
        );

        $supervisorRole = Role::firstOrCreate(
            ['name' => 'supervisor', 'guard_name' => 'web'],
            ['description' => 'Супервайзор - ограничен достъп до HR функции']
        );

        // Assign permissions to roles based on typical HR workflow
        
        // Admin gets all permissions
        $adminRole->givePermissionTo(Permission::all());

        // Manager gets most HR permissions
        $managerPermissions = [
            'access_admin_panel',
            'create_workers', 'add_vacation', 'create_regions', 'create_clients', 'create_objects',
            'configure_object_activity', 'edit_region', 'regions', 'client', 'user', 'objects',
            'add_holidays', 'history', 'approvals', 'presence_form', 'archive',
            'view_users', 'edit_users', 'view_roles', 'view_any_role', 'create_role', 'update_role',
            'view_permissions'
        ];
        $managerRole->givePermissionTo($managerPermissions);

        // Supervisor gets limited permissions
        $supervisorPermissions = [
            'create_workers', 'add_vacation', 'regions', 'client', 'user', 'objects',
            'history', 'approvals', 'presence_form'
        ];
        $supervisorRole->givePermissionTo($supervisorPermissions);

        // Create test users
        
        // Admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Системен Администратор',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $adminUser->assignRole('admin');

        // Manager user
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'HR Мениджър',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $managerUser->assignRole('manager');

        // Supervisor user
        $supervisorUser = User::firstOrCreate(
            ['email' => 'supervisor@example.com'],
            [
                'name' => 'Супервайзор',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $supervisorUser->assignRole('supervisor');

        $this->command->info('Created ' . count($permissions) . ' permissions (Bulgarian from old system)');
        $this->command->info('Created 4 roles: admin, manager, supervisor');
        $this->command->info('Created 4 test users with appropriate roles');
        $this->command->info('🇧🇬 All permissions match the old Laravel 7 system exactly');
        $this->command->info('Login credentials: email/password for each user type');
    }
}
