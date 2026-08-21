<?php

namespace App\Mail;

use App\Models\Entity;
use App\Services\ShopMailFromResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DomainActivationRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public Entity $entity;
    public string $requestedDomain;
    public ?string $notes;

    public function __construct(Entity $entity, string $requestedDomain, ?string $notes = null)
    {
        $this->entity = $entity;
        $this->requestedDomain = $requestedDomain;
        $this->notes = $notes;
    }

    public function envelope(): Envelope
    {
        $from = app(ShopMailFromResolver::class)->resolve($this->entity);

        return new Envelope(
            from: new Address($from['address'], $from['name']),
            subject: 'activation de domaine',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.domain_activation_request',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
