<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (!Schema::hasColumn('entities', 'fayko_public_key')) {
                $table->string('fayko_public_key')->nullable();
            }
            if (!Schema::hasColumn('entities', 'fayko_secret_key')) {
                $table->string('fayko_secret_key')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'fayko_public_key')) {
                $table->dropColumn('fayko_public_key');
            }
            if (Schema::hasColumn('entities', 'fayko_secret_key')) {
                $table->dropColumn('fayko_secret_key');
            }
        });
    }
};
