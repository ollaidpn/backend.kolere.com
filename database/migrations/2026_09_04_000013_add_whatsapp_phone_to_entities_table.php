<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (!Schema::hasColumn('entities', 'ccphone2')) {
                $table->string('ccphone2', 10)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('entities', 'phone2')) {
                $table->string('phone2', 30)->nullable()->after('ccphone2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            if (Schema::hasColumn('entities', 'ccphone2')) {
                $table->dropColumn('ccphone2');
            }
            if (Schema::hasColumn('entities', 'phone2')) {
                $table->dropColumn('phone2');
            }
        });
    }
};
