<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Listings originally supported a single photo (`photo_path`). The
    // "List a property" form now uploads up to three, so the property
    // detail carousel on the category pages can show a real gallery
    // instead of falling back to stock/placeholder images. `photo_path`
    // is kept as-is (first photo) for backward compatibility with any
    // existing rows/integrations; these two new columns just extend it.
    public function up(): void
    {
        Schema::table('property_submissions', function (Blueprint $table) {
            $table->string('photo_path_2')->nullable()->after('photo_path');
            $table->string('photo_path_3')->nullable()->after('photo_path_2');
        });
    }

    public function down(): void
    {
        Schema::table('property_submissions', function (Blueprint $table) {
            $table->dropColumn(['photo_path_2', 'photo_path_3']);
        });
    }
};
