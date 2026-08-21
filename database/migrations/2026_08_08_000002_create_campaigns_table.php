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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->string('type'); // email, sms, app (push)
            $table->string('title');
            $table->text('message');
            $table->json('send_to'); // List of recipients with details/status
            $table->string('status')->default('Envoyé'); // Envoyé, Programmé, Brouillon
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            // Foreign keys / indexes
            $table->foreign('entity_id')->references('id')->on('entities')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
