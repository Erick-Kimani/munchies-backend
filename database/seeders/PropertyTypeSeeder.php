<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PropertyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PropertyType::updateOrCreate(
            ['id' => 1],
            [
                'name' => 'Land/Plots',
                'slug' => 'land',
                'description' => 'Plots and parcels of land for sale or lease.',
                'is_active' => true,
            ]
        );

        PropertyType::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Rent',
                'slug' => 'rent',
                'description' => 'Properties available for rent.',
                'is_active' => true,
            ]
        );

        PropertyType::updateOrCreate(
            ['id' => 3],
            [
                'name' => 'Commercial Buildings',
                'slug' => 'commercial-buildings',
                'description' => 'Commercial buildings and business premises.',
                'is_active' => true,
            ]
        );

        PropertyType::updateOrCreate(
            ['id' => 4],
            [
                'name' => 'Apartments',
                'slug' => 'apartments',
                'description' => 'Apartments and flats for sale or rent.',
                'is_active' => true,
            ]
        );
    }
}
