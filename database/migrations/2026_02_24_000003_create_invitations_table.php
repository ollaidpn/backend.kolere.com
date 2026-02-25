<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('entity_id');
            $table->string('email');
            $table->string('name');
            $table->string('ccphone')->nullable();
            $table->string('phone')->nullable();
            $table->string('token')->unique();
            $table->enum('status', ['pending', 'accepted', 'refused'])->default('pending');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();

            $table->foreign('entity_id')->references('id')->on('entities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
