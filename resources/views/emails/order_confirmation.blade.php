<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification de commande</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #0f172a; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 800; }
        .header p { margin: 6px 0 0; font-size: 13px; color: #94a3b8; }
        .content { padding: 24px; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700; background: #e0e7ff; color: #3730a3; margin-bottom: 16px; }
        .section-title { font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px; }
        .info-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
        .info-table td { padding: 6px 0; }
        .info-table td.label { color: #64748b; width: 40%; }
        .info-table td.val { font-weight: 600; color: #0f172a; }
        .items-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 20px; }
        .items-table th { background: #f8fafc; text-align: left; padding: 10px; color: #475569; font-size: 12px; font-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .items-table td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; }
        .totals-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 10px; }
        .totals-table td { padding: 6px 10px; text-align: right; }
        .totals-table tr.total-row td { font-size: 16px; font-weight: 800; color: #0f172a; border-top: 2px solid #0f172a; padding-top: 10px; }
        .footer { background: #f8fafc; padding: 16px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ $entity->name ?: 'Boutique' }}</h1>
            <p>{{ $recipientType === 'admin' ? 'Nouvelle commande reçue en boutique' : 'Merci pour votre commande !' }}</p>
        </div>

        <div class="content">
            @if($recipientType === 'admin')
                <div class="badge">Alerte Nouvelle Commande Admin</div>
                <p style="font-size: 14px; margin-bottom: 20px;">Une nouvelle commande vient d'être passée par <strong>{{ data_get($order->client_infos, 'name') }}</strong>.</p>
            @else
                <p style="font-size: 14px; margin-bottom: 20px;">Bonjour <strong>{{ data_get($order->client_infos, 'name') }}</strong>,<br>Nous avons bien reçu votre commande. Un agent commercial vous contactera rapidement pour la confirmation.</p>
            @endif

            <div class="section-title">Détails de la commande</div>
            <table class="info-table">
                <tr>
                    <td class="label">Référence :</td>
                    <td class="val">{{ $order->reference }}</td>
                </tr>
                <tr>
                    <td class="label">Date :</td>
                    <td class="val">{{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i') }}</td>
                </tr>
                <tr>
                    <td class="label">Client :</td>
                    <td class="val">{{ data_get($order->client_infos, 'name') }} ({{ data_get($order->client_infos, 'ccphone') }} {{ data_get($order->client_infos, 'phone') }})</td>
                </tr>
                <tr>
                    <td class="label">Email :</td>
                    <td class="val">{{ data_get($order->client_infos, 'email') }}</td>
                </tr>
                <tr>
                    <td class="label">Adresse de livraison :</td>
                    <td class="val">{{ data_get($order->client_infos, 'address') ?: 'Non précisée' }} ({{ data_get($order->client_infos, 'city') }})</td>
                </tr>
                <tr>
                    <td class="label">Mode de paiement :</td>
                    <td class="val" style="text-transform: capitalize;">
                        @if($order->payment_method === 'recorded')
                            Paiement à la livraison
                        @elseif($order->paid_by === 'wave_senegal' || $order->paid_by === 'wave_sn')
                            Wave Sénégal
                        @elseif($order->paid_by === 'orange_money_senegal' || $order->paid_by === 'orange_money_sn')
                            Orange Money Sénégal
                        @else
                            Paiement en ligne
                        @endif
                    </td>
                </tr>
            </table>

            <div class="section-title">Articles commandés</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th style="text-align: center;">Qté</th>
                        <th style="text-align: right;">Prix unitaire</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td><strong>{{ data_get($item, 'name') }}</strong></td>
                            <td style="text-align: center;">{{ data_get($item, 'quantity') }}</td>
                            <td style="text-align: right;">{{ number_format(data_get($item, 'price', 0), 0, ',', ' ') }} FCFA</td>
                            <td style="text-align: right;">{{ number_format(data_get($item, 'total', 0), 0, ',', ' ') }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="totals-table">
                <tr>
                    <td style="color: #64748b;">Sous-total :</td>
                    <td style="font-weight: 600;">{{ number_format($order->amount, 0, ',', ' ') }} FCFA</td>
                </tr>
                @if($order->discount > 0)
                    <tr>
                        <td style="color: #059669; font-weight: 600;">Remise code promo :</td>
                        <td style="color: #059669; font-weight: 700;">-{{ number_format($order->discount, 0, ',', ' ') }} FCFA</td>
                    </tr>
                @endif
                <tr class="total-row">
                    <td>Total TTC :</td>
                    <td>{{ number_format($order->total, 0, ',', ' ') }} FCFA</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ $entity->name ?: 'Boutique' }}. Tous droits réservés.
        </div>
    </div>
</body>
</html>
