<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ config('app.name') }}</title>
</head>
{{--
  Template email "pur HTML" : tableaux + CSS inline uniquement, aucune
  dépendance à Illuminate\Notifications\Messages\MailMessage (markdown).
  Les clients mail (Gmail, Outlook, Apple Mail...) suppriment souvent les
  balises <style> externes et le CSS moderne (flexbox/grid) — d'où l'usage
  de <table> et de styles inline partout, seule approche fiable sur 100%
  des clients mail.
--}}
<body style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9; padding:32px 16px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.06);">

          {{-- Header : logo + nom --}}
          <tr>
            <td style="background-color:#4f46e5; padding:28px 32px; text-align:center;">
              <img
                src="{{ asset('images/logo.png') }}"
                alt="{{ config('app.name') }}"
                width="48"
                height="48"
                style="display:block; margin:0 auto 12px auto; border-radius:12px;"
              >
              <span style="color:#ffffff; font-size:18px; font-weight:700; letter-spacing:-0.02em;">
                SMS Gateway
              </span>
            </td>
          </tr>

          {{-- Contenu --}}
          <tr>
            <td style="padding:36px 32px;">
              @yield('content')
            </td>
          </tr>

          {{-- Footer --}}
          <tr>
            <td style="padding:24px 32px; background-color:#f8fafc; border-top:1px solid #e2e8f0;">
              <p style="margin:0 0 6px 0; font-size:12px; color:#94a3b8; text-align:center;">
                {{ config('app.name') }} — Passerelle SMS Android SaaS
              </p>
              <p style="margin:0; font-size:11px; color:#cbd5e1; text-align:center;">
                Cotonou, Bénin · Cet email vous a été envoyé automatiquement, merci de ne pas y répondre directement.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
