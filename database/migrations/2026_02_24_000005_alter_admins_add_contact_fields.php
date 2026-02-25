<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('ccphone')->nullable()->after('reference');
            $table->string('phone')->nullable()->after('ccphone');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn(['ccphone', 'phone']);
        });
    }
};
