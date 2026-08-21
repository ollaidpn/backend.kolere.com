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
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('file');
            $table->text('path')->nullable();
            $table->string('type')->nullable();
            $table->string('extension')->nullable();
            $table->bigInteger('size_byte')->nullable();
            $table->string('size_name')->nullable();
            $table->boolean('is_private')->default(false);
            $table->string('reference')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            
            $table->index('type');
            $table->index('reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
