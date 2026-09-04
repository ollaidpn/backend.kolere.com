@extends('layouts.mail', ['shopName' => $shopName, 'headerSubtitle' => 'Invitation à l\'espace Manager'])

@section('content')
    <h2 style="margin-top: 0; color: #0f172a;">Bonjour {{ $invitation->name }},</h2>
    
    <p>Vous avez été invité(e) à rejoindre l'équipe de gestion de <strong>{{ $shopName }}</strong> sur la plateforme Kolere.</p>
    
    <p>Afin d'activer votre accès manager et de configurer votre mot de passe, veuillez cliquer sur le bouton ci-dessous :</p>

    <div style="text-align: center; margin: 28px 0;">
        <a href="{{ $inviteLink }}" class="btn" style="background-color: #0f172a; color: #ffffff !important; padding: 12px 28px; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-block;">Activer mon compte manager</a>
    </div>

    <p style="font-size: 12px; color: #64748b; margin-top: 20px;">
        Si le bouton ne fonctionne pas, vous pouvez copier et coller le lien suivant dans votre navigateur :<br>
        <a href="{{ $inviteLink }}" style="color: #0284c7; word-break: break-all;">{{ $inviteLink }}</a>
    </p>

    <p style="margin-bottom: 0; margin-top: 24px;">À très bientôt,<br><strong>L'équipe {{ $shopName }}</strong></p>
@endsection
