<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('logo')->nullable()->change();
            $table->string('primary_color')->nullable()->change();
            $table->string('secondary_color')->nullable()->change();
            $table->string('email')->nullable()->after('country');
            $table->string('ccphone')->nullable()->after('email');
            $table->string('phone')->nullable()->after('ccphone');
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('logo')->nullable(false)->change();
            $table->string('primary_color')->nullable(false)->change();
            $table->string('secondary_color')->nullable(false)->change();
            $table->dropColumn(['email', 'ccphone', 'phone']);
        });
    }
};
