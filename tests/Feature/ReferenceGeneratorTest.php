<?php

namespace Tests\Feature;

use App\Models\ShopItem;
use App\Services\ReferenceGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferenceGeneratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('reference_counters', function (Blueprint $table) {
            $table->string('type')->primary();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->timestamps();
        });

        Schema::create('shop_items', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->timestamps();
        });

        DB::table('reference_counters')->insert([
            'type' => 'shop_items',
            'current_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_it_generates_sequential_unique_references(): void
    {
        $generator = app(ReferenceGenerator::class);

        $this->assertSame('I00001', $generator->generate('shop_items', 'I'));
        DB::table('shop_items')->insert(['reference' => 'I00001']);

        $this->assertSame('I00002', $generator->generate('shop_items', 'I'));
    }

    public function test_it_skips_a_reference_already_present_in_the_table(): void
    {
        DB::table('shop_items')->insert([
            ['reference' => 'I00001'],
            ['reference' => 'I00002'],
        ]);

        $reference = app(ReferenceGenerator::class)->generate('shop_items', 'I');

        $this->assertSame('I00003', $reference);
        $this->assertDatabaseMissing('shop_items', ['reference' => $reference]);
        $this->assertDatabaseHas('reference_counters', [
            'type' => 'shop_items',
            'current_value' => 3,
        ]);
    }

    public function test_the_model_generates_its_reference_automatically(): void
    {
        $item = new ShopItem();
        $item->save();

        $this->assertSame('I00001', $item->reference);
        $this->assertDatabaseHas('shop_items', ['reference' => 'I00001']);
    }
}
