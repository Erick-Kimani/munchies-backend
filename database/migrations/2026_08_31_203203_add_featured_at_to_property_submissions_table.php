<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_submissions', function (Blueprint $table) {
            // Set the moment a submission is featured, cleared when it's
            // unfeatured/rejected. A listing is auto pulled back to
            // 'pending' one calendar month after this timestamp — see
            // App\Console\Commands\UnfeatureExpiredListings.
            $table->timestamp('featured_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('property_submissions', function (Blueprint $table) {
            $table->dropColumn('featured_at');
        });
    }
};