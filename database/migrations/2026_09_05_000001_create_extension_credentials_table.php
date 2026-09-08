<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('extension_credentials')) {
            Schema::create('extension_credentials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
                $table->string('extension')->default('meditect'); // 'meditect', etc.
                $table->json('data')->nullable(); // Contient api_key, email, password, pin, etc.
                $table->json('config')->nullable(); // Contient la configuration (ex: selected_rayons)
                $table->boolean('status')->default(false); // true = actif, false = inactif
                $table->timestamps();

                $table->unique(['entity_id', 'extension'], 'ext_credentials_entity_ext_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extension_credentials');
    }
};
