<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('website_status')->nullable()->default('inactive')->after('subdomain');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropColumn('website_status');
        });
    }
};
