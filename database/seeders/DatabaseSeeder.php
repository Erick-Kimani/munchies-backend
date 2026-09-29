<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PropertyTypeSeeder::class,
            CountySeeder::class, // Added the missing property type seeder here
        ]);

        // SECURITY: this used to hard-code an admin email + a plaintext
        // password directly in source control, and `updateOrCreate` meant
        // every re-seed silently reset that account back to the
        // hard-coded password even if it had since been changed. See
        // AdminUserSeeder for the replacement: it only acts on explicit
        // ADMIN_EMAIL / ADMIN_PASSWORD environment variables, and never
        // overwrites an existing password.
        $this->call(AdminUserSeeder::class);
    }
}