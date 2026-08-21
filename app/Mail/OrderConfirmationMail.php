<?php

namespace App\Mail;

use App\Models\Entity;
use App\Models\ShopOrder;
use App\Services\ShopMailFromResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public ShopOrder $order;
    public Entity $entity;
    public string $recipientType; // 'client' or 'admin'

    public function __construct(ShopOrder $order, Entity $entity, string $recipientType = 'client')
    {
        $this->order = $order;
        $this->entity = $entity;
        $this->recipientType = $recipientType;
    }

    public function envelope(): Envelope
    {
        $storeName = $this->entity->name ?: 'Boutique';
        $clientName = data_get($this->order->client_infos, 'name') ?: 'Client';
        $from = app(ShopMailFromResolver::class)->resolve($this->entity);

        if ($this->recipientType === 'admin') {
            $subject = "Nouvelle commande de : {$clientName} | {$storeName}";
        } else {
            $subject = "Commande reçue | {$storeName}";
        }

        return new Envelope(
            from: new Address($from['address'], $from['name']),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order_confirmation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
