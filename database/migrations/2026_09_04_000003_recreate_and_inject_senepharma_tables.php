<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // 1. Table `entities`
        if (!Schema::hasTable('entities')) {
            Schema::create('entities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('reference')->nullable()->unique();
                $table->string('subdomain')->nullable()->unique();
                $table->string('custom_domain')->nullable()->unique();
                $table->string('website_status')->default('active');
                $table->unsignedBigInteger('domain_id')->nullable();
                $table->string('name');
                $table->string('type')->default('pharmacy');
                $table->string('logo')->nullable();
                $table->string('primary_color')->default('#0D9488');
                $table->string('secondary_color')->default('#F0FDFA');
                $table->json('web_slider')->nullable();
                $table->string('address')->nullable();
                $table->string('town')->nullable();
                $table->string('country')->nullable();
                $table->string('email')->nullable();
                $table->string('ccphone')->nullable();
                $table->string('phone')->nullable();
                $table->string('ccphone2')->nullable();
                $table->string('phone2')->nullable();
                $table->json('delivery_zones')->nullable();
                $table->string('diotko_public_key')->nullable();
                $table->string('diotko_secret_key')->nullable();
                $table->string('fayko_public_key')->nullable();
                $table->string('fayko_secret_key')->nullable();
                $table->string('fayko_webhook_key')->nullable();
                $table->string('fayko_mode')->nullable();
                $table->string('fayko_status')->nullable();
                $table->timestamps();
            });
        }

        // Injecter ou mettre à jour l'Entity avec la référence fixée ENT-0001
        $existingDomainId = DB::table('domains')->value('id') ?? 1;

        $entityId = DB::table('entities')->where('reference', 'ENT-0001')->value('id');

        if (!$entityId) {
            $entityId = DB::table('entities')->insertGetId([
                'reference'        => 'ENT-0001',
                'subdomain'        => 'senepharma',
                'name'             => 'Senepharma',
                'domain_id'        => $existingDomainId,
                'type'             => 'pharmacy',
                'website_status'   => 'active',
                'logo'             => 'logos/pharmacie-mame-diarra.png',
                'primary_color'    => '#0D9488',
                'secondary_color'  => '#F0FDFA',
                'address'          => 'Dakar, Sénégal',
                'town'             => 'Dakar',
                'country'          => 'Sénégal',
                'email'            => 'contact@senepharma.sn',
                'ccphone'          => '+221',
                'phone'            => '770000000',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        // 2. Table `shop_categories`
        if (!Schema::hasTable('shop_categories')) {
            Schema::create('shop_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
                $table->string('reference');
                $table->string('name');
                $table->timestamps();

                $table->unique('reference');
                $table->unique(['entity_id', 'name']);
            });
        }

        // 3. Table `shop_brands`
        if (!Schema::hasTable('shop_brands')) {
            Schema::create('shop_brands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
                $table->string('reference');
                $table->string('name');
                $table->string('image')->nullable();
                $table->timestamps();

                $table->unique('reference');
                $table->unique(['entity_id', 'name']);
            });
        }

        // 4. Table `shop_items`
        if (!Schema::hasTable('shop_items')) {
            Schema::create('shop_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entity_id')->constrained('entities')->cascadeOnDelete();
                $table->foreignId('category_id')->constrained('shop_categories')->cascadeOnDelete();
                $table->unsignedBigInteger('brand_id')->nullable();
                $table->string('reference');
                $table->string('name');
                $table->decimal('price', 12, 2)->default(0);
                $table->decimal('promo_price', 12, 2)->nullable();
                $table->unsignedInteger('stock')->default(0);
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->json('gallery')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();

                $table->index(['entity_id', 'category_id']);
                $table->index('brand_id');
                $table->unique('reference');
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('shop_items');
        Schema::dropIfExists('shop_brands');
        Schema::dropIfExists('shop_categories');
        Schema::dropIfExists('entities');
        Schema::enableForeignKeyConstraints();
    }
};
