<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => 'administrator'],
            ['name' => 'Seller', 'slug' => 'seller'],
            ['name' => 'User', 'slug' => 'user'],
        ];

        Role::updateOrCreate(
            ['id' => 1],
            ['name' => 'Administrator', 'slug' => 'administrator']
        );

        Role::updateOrCreate(
            ['id' => 2],
            ['name' => 'Seller', 'slug' => 'seller']
        );

        Role::updateOrCreate(
            ['id' => 3],
            ['name' => 'User', 'slug' => 'user']
        );
    }
}
