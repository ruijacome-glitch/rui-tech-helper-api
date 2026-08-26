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
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; max-width:600px; width:100%; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb;">
                    <tr>
                        <td align="center" style="background-color:#0F1B2E; padding: 28px 24px;">
                            <img src="{{ config('app.url') }}/images/logo-email.png" alt="O Rui dos Computadores" width="120" style="display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#2E7FFF; font-size:0; line-height:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="padding: 32px 28px; color:#334155; font-size:15px; line-height:1.65;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td style="border-top:1px solid #e5e7eb; font-size:0; line-height:0;">&nbsp;</td></tr></table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="background-color:#f8fafc; padding: 24px 28px;">
                            <p style="margin:0 0 6px 0; color:#0F1B2E; font-size:13px; font-weight:bold; letter-spacing:.3px;">O Rui dos Computadores</p>
                            <p style="margin:0 0 4px 0; color:#64748b; font-size:12px;">Assistência técnica informática</p>
                            <p style="margin:0 0 12px 0; color:#64748b; font-size:12px;">
                                <a href="mailto:ola@oruidoscomputadores.pt" style="color:#64748b; text-decoration:underline;">ola@oruidoscomputadores.pt</a>
                                &nbsp;&middot;&nbsp; 911 556 901
                            </p>
                            <p style="margin:0; color:#94a3b8; font-size:11px; line-height:1.5;">
                                Recebeste este email porque tens um pedido de assistência em curso connosco.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
