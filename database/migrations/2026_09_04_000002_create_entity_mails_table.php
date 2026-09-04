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
        Schema::create('entity_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
            $table->string('username');
            $table->string('at_domain');
            $table->enum('status', ['requested', 'active', 'suspended', 'deleted'])->default('requested');
            $table->string('host')->nullable();
            $table->string('server')->nullable();
            $table->text('password')->nullable();
            $table->string('webmail_link')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_id', 'username', 'at_domain'], 'entity_mails_entity_username_domain_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_mails');
    }
};
