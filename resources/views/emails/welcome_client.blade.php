@extends('layouts.mail', ['shopName' => $shopName, 'headerSubtitle' => 'Bienvenue dans notre programme de fidélité ! 🎁'])

@section('content')
    <h2 style="margin-top: 0; color: #0f172a;">Bonjour {{ $client->name }},</h2>
    
    <p>Votre compte client et votre carte de fidélité chez <strong>{{ $shopName }}</strong> ont été créés avec succès !</p>
    
    <div class="card" style="text-align: center; padding: 20px;">
        <span class="badge">Référence de votre carte</span>
        <div style="font-size: 22px; font-weight: 800; font-family: monospace; color: #0f172a; letter-spacing: 0.05em; margin-top: 6px;">
            {{ $cardRef }}
        </div>
    </div>

    <p>Vous pouvez dès à présent cumuler des points lors de vos achats et bénéficier de nombreuses réductions et récompenses exclusives.</p>

    <p style="margin-bottom: 0;">À très bientôt,<br><strong>L'équipe {{ $shopName }}</strong></p>
@endsection
