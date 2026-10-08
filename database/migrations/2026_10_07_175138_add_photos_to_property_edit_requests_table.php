<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Lets a seller propose replacement photos as part of an edit request.
// Same columns/semantics as property_submissions: NULL means "no change
// to this photo". The files stay in property-edit-requests/ until an admin
// approves (copied onto the listing) or rejects (deleted).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_edit_requests', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('phone');
            $table->string('photo_path_2')->nullable()->after('photo_path');
            $table->string('photo_path_3')->nullable()->after('photo_path_2');
        });
    }

    public function down(): void
    {
        Schema::table('property_edit_requests', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'photo_path_2', 'photo_path_3']);
        });
    }
};
