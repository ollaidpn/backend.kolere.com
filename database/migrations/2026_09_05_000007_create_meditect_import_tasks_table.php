<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meditect_import_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->foreignId('extension_credential_id')->nullable()->constrained('extension_credentials')->nullOnDelete();
            $table->foreignId('meditect_import_session_id')->nullable()->constrained('meditect_import_sessions')->nullOnDelete();
            $table->string('external_item_id');
            $table->string('status')->default('pending');
            $table->json('rayon_ids')->nullable();
            $table->json('rayon_names')->nullable();
            $table->json('summary')->nullable();
            $table->json('details')->nullable();
            $table->unsignedBigInteger('shop_item_id')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'external_item_id']);
            $table->index(['entity_id', 'status']);
            $table->index(['meditect_import_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meditect_import_tasks');
    }
};
