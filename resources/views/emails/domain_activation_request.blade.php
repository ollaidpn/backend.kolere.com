<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demande d'activation de domaine</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #0f172a; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #0f172a; color: #ffffff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 800; }
        .content { padding: 24px; }
        .badge { display: inline-block; background: #e0f2fe; color: #0369a1; font-weight: 700; padding: 4px 12px; border-radius: 9999px; font-size: 12px; margin-bottom: 16px; }
        .info-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #cbd5e1; font-size: 14px; }
        .info-row:last-child { border-bottom: none; }
        .label { color: #64748b; font-weight: 500; }
        .value { font-weight: 700; color: #0f172a; }
        .domain-box { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 16px; border-radius: 12px; text-align: center; font-size: 18px; font-weight: 900; font-family: monospace; margin-bottom: 20px; }
        .footer { background: #f1f5f9; padding: 16px; text-align: center; font-size: 12px; color: #64748b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nouvelle Demande d'Activation de Domaine</h1>
        </div>

        <div class="content">
            <span class="badge">DEVOLA / ADMIN NOTIFICATION</span>

            <p style="font-size: 14px; color: #475569; margin-bottom: 20px;">
                Une nouvelle demande d'activation de domaine personnalisé a été soumise par un établissement Kolere.
            </p>

            <div class="domain-box">
                {{ $requestedDomain }}
            </div>

            <h2 style="font-size: 15px; font-weight: 800; margin-bottom: 12px;">Informations sur la boutique</h2>
            <div class="info-card">
                <div class="info-row">
                    <span class="label">Nom boutique :</span>
                    <span class="value">{{ $entity->name }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Référence :</span>
                    <span class="value">{{ $entity->reference }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Sous-domaine actuel :</span>
                    <span class="value">{{ $entity->subdomain ? $entity->subdomain . '.kolere.com' : 'Aucun' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Email de contact :</span>
                    <span class="value">{{ $entity->email ?: 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Téléphone :</span>
                    <span class="value">{{ $entity->phone ?: 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="label">Adresse :</span>
                    <span class="value">{{ $entity->address }} {{ $entity->town }} {{ $entity->country }}</span>
                </div>
            </div>

            @if(!empty($notes))
                <h2 style="font-size: 15px; font-weight: 800; margin-bottom: 8px;">Notes & Instructions :</h2>
                <div style="background: #fffbebfb; border: 1px solid #fde68a; padding: 12px; border-radius: 8px; font-size: 13px; color: #92400e; font-style: italic;">
                    "{{ $notes }}"
                </div>
            @endif
        </div>

        <div class="footer">
            Cet email a été envoyé automatiquement par la plateforme Kolere System.
        </div>
    </div>
</body>
</html>
