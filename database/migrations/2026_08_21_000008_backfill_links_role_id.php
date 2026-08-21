<?php

use App\Models\Link;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaultRole = Role::query()->where('slug', 'boutique_manager')->first();

        Link::query()
            ->where('is_admin', true)
            ->update(['role_id' => null]);

        if ($defaultRole) {
            Link::query()
                ->where('is_admin', false)
                ->whereNull('role_id')
                ->update(['role_id' => $defaultRole->id]);
        }
    }

    public function down(): void
    {
        Link::query()
            ->where('is_admin', false)
            ->update(['role_id' => null]);
    }
};
