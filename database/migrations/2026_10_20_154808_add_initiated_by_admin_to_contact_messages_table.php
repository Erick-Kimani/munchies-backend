<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            // true  -> the admin opened this thread (the `message` column is
            //          the admin's opening message, addressed to `user_id`)
            // false -> the user opened it (existing behaviour)
            $table->boolean('initiated_by_admin')->default(false)->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropColumn('initiated_by_admin');
        });
    }
};