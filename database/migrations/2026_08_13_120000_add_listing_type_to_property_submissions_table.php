<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds `listing_type` — the seller's intent (sale vs rent) — as its own
     * column, distinct from `type` (the property category, e.g. "Apartments").
     *
     * Before this, "Rentals" was jammed into `type` as a fake category,
     * which conflated two different questions:
     *   - `type`         => what kind of property is this?
     *   - `listing_type` => does the seller want to sell it or rent it out?
     *
     * Buyers filter by `listing_type` too (?listing_type=rent on the public
     * listings endpoint) — same column, read by one role and written by
     * another, never conflated with `type`.
     */
    public function up(): void
    {
        Schema::table('property_submissions', function (Blueprint $table) {
            $table->enum('listing_type', ['sale', 'rent'])
                ->default('sale')
                ->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('property_submissions', function (Blueprint $table) {
            $table->dropColumn('listing_type');
        });
    }
};
