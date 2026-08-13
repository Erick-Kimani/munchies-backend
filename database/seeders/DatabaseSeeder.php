<?php

namespace Database\Seeders;

use App\Models\User;
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

        User::updateOrCreate(
            ['email' => 'erick@example.com'],
            [
                'name' => 'Erick Kimani',
                'password' => bcrypt('Dragon123!'), // Replace with your real secure password
                'role_id' => 1,
            ]
        );
    }
}
