<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('notification_reads')) {
            return;
        }

        Schema::table('notification_reads', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_reads', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('notification_id')->constrained('users')->cascadeOnDelete();
            }

            $table->unique(['notification_id', 'user_id']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_reads')) {
            return;
        }

        Schema::table('notification_reads', function (Blueprint $table) {
            if (Schema::hasColumn('notification_reads', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
