<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_submissions', function (Blueprint $table) {
            // Admin-granted extra edit-request slots for this one listing,
            // on top of PropertyEditRequest::MAX_PER_SUBMISSION. Only ever
            // changed by the admin-only grant endpoint, never from seller
            // input (deliberately not in $fillable).
            $table->unsignedSmallInteger('extra_edit_requests')->default(0)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('property_submissions', function (Blueprint $table) {
            $table->dropColumn('extra_edit_requests');
        });
    }
};
