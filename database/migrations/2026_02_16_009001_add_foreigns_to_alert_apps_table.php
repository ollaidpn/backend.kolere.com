<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('alert_apps', function (Blueprint $table) {
            $table
                ->foreign('entity_id')
                ->references('id')
                ->on('entities')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            $table
                ->foreign('card_id')
                ->references('id')
                ->on('cards')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');

            $table
                ->foreign('manager_id')
                ->references('id')
                ->on('managers')
                ->onUpdate('CASCADE')
                ->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alert_apps', function (Blueprint $table) {
            $table->dropForeign(['entity_id']);
            $table->dropForeign(['card_id']);
            $table->dropForeign(['manager_id']);
        });
    }
};
