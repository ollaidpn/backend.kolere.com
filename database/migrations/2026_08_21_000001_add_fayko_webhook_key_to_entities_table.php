<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (!Schema::hasColumn('entities', 'fayko_webhook_key')) {
                $table->string('fayko_webhook_key')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'fayko_webhook_key')) {
                $table->dropColumn('fayko_webhook_key');
            }
        });
    }
};
