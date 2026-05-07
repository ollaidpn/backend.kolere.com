<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->unsignedBigInteger('card_id')->nullable()->after('user_id');
            $table->string('reference')->nullable()->unique()->after('card_id');
            $table->json('items')->nullable()->after('reference');
            $table->integer('points_earned')->default(0)->after('items');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('card_id')->references('id')->on('cards')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['card_id']);
            $table->dropColumn(['user_id', 'card_id', 'reference', 'items', 'points_earned']);
        });
    }
};
