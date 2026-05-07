<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('description')->nullable();
            $table->string('photo')->nullable();
            $table->enum('status', ['pending', 'available', 'unavailable'])->default('pending');
            $table->text('manager_comment')->nullable();
            $table->decimal('manager_amount', 15, 2)->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
