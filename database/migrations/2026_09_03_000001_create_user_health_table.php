<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_health', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Fiche médicale
            $table->string('blood_type', 5)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->integer('height_cm')->nullable();
            
            $table->text('medical_history')->nullable();
            $table->text('chronic_diseases')->nullable();
            $table->text('current_treatments')->nullable();
            $table->text('emergency_notes')->nullable();
            
            // Contact d'urgence
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();

            // Allergies (JSON)
            $table->json('allergies')->nullable();

            $table->timestamps();
            
            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_health');
    }
};
