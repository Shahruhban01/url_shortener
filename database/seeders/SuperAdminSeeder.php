<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env(
            'SUPERADMIN_EMAIL',
            'superadmin@gmail.com'
        );

        $password = env('SUPERADMIN_PASSWORD');

        if (empty($password)) {
            throw new RuntimeException(
                'Set SUPERADMIN_PASSWORD in your .env file before seeding.'
            );
        }

        $existing = DB::table('users')
            ->where('email', $email)
            ->first();

        if ($existing) {
            if ($existing->role !== 'super_admin') {
                throw new RuntimeException(
                    'The configured SuperAdmin email already belongs to a non-SuperAdmin user.'
                );
            }

            return;
        }

        DB::insert(
            'INSERT INTO users
                (company_id, name, email, password, role, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                null,
                'Super Admin',
                $email,
                Hash::make($password),
                'super_admin',
                now(),
                now(),
            ]
        );
    }
}
