<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\BackofficeRole;
use App\Models\Permission;
use App\Models\User;
use App\Services\Backoffice\PermissionResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BackofficeRbacSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('backoffice.permissions') as $perm) {
            Permission::query()->updateOrCreate(
                ['key' => $perm['key']],
                [
                    'name' => $perm['name'],
                    'group' => $perm['group'] ?? null,
                ],
            );
        }

        $resolver = app(PermissionResolver::class);

        foreach (config('backoffice.roles') as $roleDef) {
            $role = BackofficeRole::query()->updateOrCreate(
                ['slug' => $roleDef['slug']],
                [
                    'name' => $roleDef['name'],
                    'department' => $roleDef['department'] ?? null,
                    'is_system' => $roleDef['is_system'] ?? false,
                ],
            );

            $matrix = config("backoffice.role_matrix.{$roleDef['slug']}", []);
            $resolver->syncRolePermissions($role, $matrix);
        }

        $superAdminRole = BackofficeRole::query()->where('slug', 'super_admin')->first();

        if ($superAdminRole) {
            User::query()->updateOrCreate(
                ['email' => 'admin@iwallet.demo'],
                [
                    'firstname' => 'System',
                    'lastname' => 'Administrator',
                    'password' => Hash::make('Password123!'),
                    'user_type' => UserType::Staff,
                    'backoffice_role_id' => $superAdminRole->id,
                    'role' => 'admin',
                    'status' => UserStatus::Approved,
                    'job_title' => 'Super Admin',
                    'email_verified_at' => now(),
                ],
            );

            User::query()
                ->where('role', 'admin')
                ->where('email', '!=', 'admin@iwallet.demo')
                ->whereNull('backoffice_role_id')
                ->each(function (User $user) use ($superAdminRole) {
                    $user->update([
                        'user_type' => UserType::Staff,
                        'backoffice_role_id' => $superAdminRole->id,
                        'status' => UserStatus::Approved,
                        'job_title' => $user->job_title ?? 'Super Admin',
                    ]);
                });
        }

        $demoStaff = [
            ['email' => 'agent.manager@iwallet.demo', 'slug' => 'agent_manager', 'firstname' => 'Kelechi', 'lastname' => 'Nnaji', 'job_title' => 'Regional agent manager', 'hub' => 'South-East'],
            ['email' => 'aml.analyst@iwallet.demo', 'slug' => 'aml_analyst', 'firstname' => 'Tolu', 'lastname' => 'Akinbode', 'job_title' => 'AML analyst', 'hub' => 'Lagos hub'],
            ['email' => 'compliance@iwallet.demo', 'slug' => 'compliance_officer', 'firstname' => 'Funmi', 'lastname' => 'Adekunle', 'job_title' => 'Chief compliance officer', 'hub' => null],
            ['email' => 'support@iwallet.demo', 'slug' => 'customer_support', 'firstname' => 'Bola', 'lastname' => 'Adesina', 'job_title' => 'Tier 1 support agent', 'hub' => 'Online'],
            ['email' => 'finance@iwallet.demo', 'slug' => 'finance_treasury', 'firstname' => 'Amaka', 'lastname' => 'Iheanacho', 'job_title' => 'Treasury manager', 'hub' => 'Lagos hub'],
            ['email' => 'operations@iwallet.demo', 'slug' => 'operations_lead', 'firstname' => 'Sade', 'lastname' => 'Bankole', 'job_title' => 'Operations lead', 'hub' => 'Lagos hub'],
            ['email' => 'settlement@iwallet.demo', 'slug' => 'settlement_officer', 'firstname' => 'Ngozi', 'lastname' => 'Okeke', 'job_title' => 'Settlement officer', 'hub' => 'Lagos hub'],
        ];

        foreach ($demoStaff as $staff) {
            $role = BackofficeRole::query()->where('slug', $staff['slug'])->first();
            if (! $role) {
                continue;
            }

            User::query()->updateOrCreate(
                ['email' => $staff['email']],
                [
                    'firstname' => $staff['firstname'],
                    'lastname' => $staff['lastname'],
                    'password' => Hash::make('Password123!'),
                    'user_type' => UserType::Staff,
                    'backoffice_role_id' => $role->id,
                    'role' => 'admin',
                    'status' => UserStatus::Approved,
                    'job_title' => $staff['job_title'],
                    'hub' => $staff['hub'],
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
