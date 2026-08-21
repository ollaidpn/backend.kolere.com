<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firebase_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('manager_id')->nullable()->constrained('managers')->onDelete('cascade');
            $table->foreignId('admin_id')->nullable()->constrained('admins')->onDelete('cascade');
            $table->text('token');
            $table->string('device_type', 20)->nullable()->comment('ios, android');
            $table->string('device_id', 255)->nullable();
            $table->string('device_name', 255)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->string('app_platform', 10)->nullable()->comment('ios, android');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['manager_id', 'is_active']);
            $table->index(['admin_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firebase_tokens');
    }
};
