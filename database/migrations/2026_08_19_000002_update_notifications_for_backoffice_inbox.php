<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            try {
                DB::statement('ALTER TABLE notifications MODIFY user_id BIGINT UNSIGNED NULL');
            } catch (\Throwable $e) {
                // Fallback for non-MySQL environments during local development.
            }

            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications', 'type')) {
                    $table->string('type')->default('system')->after('message');
                }

                if (!Schema::hasColumn('notifications', 'manager_id')) {
                    $table->foreignId('manager_id')->nullable()->after('user_id')->constrained('managers')->nullOnDelete();
                }

                if (!Schema::hasColumn('notifications', 'read_at')) {
                    $table->timestamp('read_at')->nullable()->after('is_read');
                }
            });
        }

        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('managers')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'manager_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (Schema::hasColumn('notifications', 'manager_id')) {
                    $table->dropConstrainedForeignId('manager_id');
                }

                if (Schema::hasColumn('notifications', 'read_at')) {
                    $table->dropColumn('read_at');
                }

                if (Schema::hasColumn('notifications', 'type')) {
                    $table->dropColumn('type');
                }
            });

            try {
                DB::statement('ALTER TABLE notifications MODIFY user_id BIGINT UNSIGNED NOT NULL');
            } catch (\Throwable $e) {
                // Ignore in non-MySQL local environments.
            }
        }
    }
};
