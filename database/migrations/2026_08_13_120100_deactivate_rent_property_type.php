<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Rent" (slug: rent) was seeded as a property_type alongside real
     * categories like "Apartments" and "Commercial Buildings" — a leftover
     * from before `listing_type` existed. It answers "sale or rent?", not
     * "what kind of property?", so it doesn't belong in this table.
     *
     * Deactivated rather than deleted: any existing submission still
     * carrying this type (from before this change) keeps resolving its
     * type name correctly, it just stops showing up in new dropdowns.
     */
    public function up(): void
    {
        DB::table('property_types')
            ->where('slug', 'rent')
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        DB::table('property_types')
            ->where('slug', 'rent')
            ->update(['is_active' => true]);
    }
};
