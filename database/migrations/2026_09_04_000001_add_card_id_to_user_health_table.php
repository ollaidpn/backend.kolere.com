<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_health')) {
            Schema::table('user_health', function (Blueprint $table) {
                if (!Schema::hasColumn('user_health', 'card_id')) {
                    $table->foreignId('card_id')->nullable()->after('id')->constrained('cards')->onDelete('cascade');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_health')) {
            Schema::table('user_health', function (Blueprint $table) {
                if (Schema::hasColumn('user_health', 'card_id')) {
                    $table->dropForeign(['card_id']);
                    $table->dropColumn('card_id');
                }
            });
        }
    }
};
