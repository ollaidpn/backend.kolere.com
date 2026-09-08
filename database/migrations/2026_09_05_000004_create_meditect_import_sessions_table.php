<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meditect_import_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('extension_credential_id')->nullable()->constrained('extension_credentials')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('cursor')->nullable();
            $table->json('buffer')->nullable();
            $table->unsignedInteger('buffer_index')->default(0);
            $table->unsignedInteger('batch_size')->default(10);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('total_count')->default(0);
            $table->json('selected_rayons_snapshot')->nullable();
            $table->json('meta')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->unique('entity_id');
            $table->index(['status', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meditect_import_sessions');
    }
};
