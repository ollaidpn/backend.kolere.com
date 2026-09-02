<?php

namespace Tests\Unit;

use App\Mail\ManagerInvitationMail;
use App\Models\Entity;
use App\Models\Invitation;
use Tests\TestCase;

class ManagerInvitationMailTest extends TestCase
{
    public function test_it_renders_the_html_invitation_template(): void
    {
        config()->set('mail.from.address', 'support@parakhadijaba.com');

        $entity = new Entity();
        $entity->name = 'Shop Mame Diarra';

        $invitation = new Invitation();
        $invitation->name = 'Awa Ndiaye';

        $html = (new ManagerInvitationMail(
            $invitation,
            'https://example.test/invitation/token',
            $entity,
        ))->render();

        $this->assertStringContainsString('Shop Mame Diarra', $html);
        $this->assertStringContainsString('Bonjour <strong>Awa Ndiaye</strong>', $html);
        $this->assertStringContainsString('Accepter l’invitation', $html);
        $this->assertStringContainsString('https://example.test/invitation/token', $html);
    }
}
