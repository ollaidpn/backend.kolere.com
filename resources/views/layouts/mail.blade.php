<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? $subject ?? config('app.name') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            margin: 0;
            padding: 30px 15px;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
        }
        .header {
            background: #0f172a;
            color: #ffffff;
            padding: 28px 24px;
            text-align: center;
        }
        .header-logo {
            max-height: 48px;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: #ffffff;
        }
        .header p {
            margin: 6px 0 0;
            font-size: 13px;
            color: #94a3b8;
            font-weight: 500;
        }
        .content {
            padding: 32px 28px;
            line-height: 1.6;
            font-size: 14px;
            color: #334155;
        }
        .footer {
            background: #f8fafc;
            padding: 20px 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
            line-height: 1.5;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            background: #e0e7ff;
            color: #3730a3;
            margin-bottom: 16px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            margin: 16px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- HEADER GLOBAL -->
        <div class="header">
            @if(!empty($shopLogo))
                <img src="{{ $shopLogo }}" alt="{{ $shopName ?? config('app.name') }}" class="header-logo">
            @endif
            <h1>{{ $shopName ?? config('app.name') }}</h1>
            @if(!empty($headerSubtitle))
                <p>{{ $headerSubtitle }}</p>
            @endif
        </div>

        <!-- BODY DU MAIL -->
        <div class="content">
            @yield('content')
        </div>

        <!-- FOOTER GLOBAL -->
        <div class="footer">
            <p style="margin: 0 0 6px 0;">
                &copy; {{ date('Y') }} <strong>{{ $shopName ?? config('app.name') }}</strong>. Tous droits réservés.
            </p>
            <p style="margin: 0; font-size: 11px; color: #94a3b8;">
                Cet e-mail a été envoyé automatiquement par la plateforme Kolere.
            </p>
        </div>
    </div>
</body>
</html>
