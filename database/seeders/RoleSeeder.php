<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'ADMINISTRATOR', 'slug' => 'administrator'],
            ['name' => 'Seller', 'slug' => 'seller'],
            ['name' => 'Buyer', 'slug' => 'buyer'],
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
