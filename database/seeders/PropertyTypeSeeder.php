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

        // "Rent" here answers "sale or rent?", not "what kind of property is
        // this?" — that question is now handled by `listing_type` on
        // property_submissions, not by a fake category in this table. Kept
        // (not deleted) for any pre-existing data that still references it,
        // but seeded inactive so it never appears in new dropdowns.
        PropertyType::updateOrCreate(
            ['id' => 2],
            [
                'name' => 'Rental',
                'slug' => 'rental',
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
