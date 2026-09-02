<?php

namespace Tests\Unit;

use App\Models\Entity;
use App\Services\ShopMailFromResolver;
use Tests\TestCase;

class ShopMailFromResolverTest extends TestCase
{
    public function test_it_uses_the_authenticated_mailbox_as_sender(): void
    {
        config()->set('mail.from.address', 'support@parakhadijaba.com');
        config()->set('mail.from.name', 'Kolere');

        $entity = new Entity();
        $entity->email = 'shop.mamediarra@kolere.com';
        $entity->name = 'Shop Mame Diarra';

        $from = app(ShopMailFromResolver::class)->resolve($entity);

        $this->assertSame('support@parakhadijaba.com', $from['address']);
        $this->assertSame('Shop Mame Diarra', $from['name']);
        $this->assertSame($entity, $from['entity']);
    }
}
