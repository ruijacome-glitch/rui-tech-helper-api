{{-- resources/views/emails/layout.blade.php --}}
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('subject', 'O Rui dos Computadores')</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding: 24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; max-width:600px; width:100%; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td align="center" style="background-color:#0F1B2E; padding: 24px;">
                            <img src="{{ config('app.url') }}/images/logo-email.png" alt="O Rui dos Computadores" width="120" style="display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 24px; color:#1a1a1a; font-size:15px; line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:#f4f4f5; padding: 16px 24px; color:#6b7280; font-size:12px;">
                            O Rui dos Computadores &middot; ola@oruidoscomputadores.pt
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
