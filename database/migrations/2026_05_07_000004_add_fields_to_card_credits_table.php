<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('card_credits', function (Blueprint $table) {
            if (!Schema::hasColumn('card_credits', 'points')) {
                $table->integer('points')->default(0)->after('credit');
            }
            if (!Schema::hasColumn('card_credits', 'type')) {
                $table->string('type')->default('earned')->after('points');
            }
            if (!Schema::hasColumn('card_credits', 'description')) {
                $table->string('description')->nullable()->after('type');
            }
            if (!Schema::hasColumn('card_credits', 'reward_id')) {
                $table->unsignedBigInteger('reward_id')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('card_credits', function (Blueprint $table) {
            $table->dropColumn(['points', 'type', 'description', 'reward_id']);
        });
    }
};
