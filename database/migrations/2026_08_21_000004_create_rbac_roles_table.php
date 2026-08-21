<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->enum('user_type', ['admin', 'shop'])->default('shop');
            $table->foreignId('entity_id')->nullable()->constrained('entities')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->enum('scope', ['global', 'entity'])->default('global');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
