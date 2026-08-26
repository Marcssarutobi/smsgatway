@extends('emails.layout')

@section('content')
  <h1 style="margin:0 0 16px 0; font-size:20px; font-weight:700; color:#0f172a;">
    Merci pour votre confiance !
  </h1>

  <p style="margin:0 0 20px 0; font-size:14px; line-height:1.6; color:#475569;">
    @if($isPaid)
      Votre paiement a été confirmé et votre plan <strong>{{ $planName }}</strong> est maintenant actif.
    @else
      Votre plan <strong>{{ $planName }}</strong> est maintenant actif.
    @endif
  </p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
         style="background-color:#f8fafc; border-radius:10px; margin:0 0 24px 0;">
    <tr>
      <td style="padding:16px 20px;">
        <p style="margin:0; font-size:12px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:0.04em;">
          Quota mensuel
        </p>
        <p style="margin:4px 0 0 0; font-size:22px; font-weight:700; color:#0f172a;">
          {{ number_format($smsQuota, 0, ',', ' ') }} SMS
        </p>
      </td>
    </tr>
  </table>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px 0;">
    <tr>
      <td style="border-radius:10px; background-color:#4f46e5;">
        <a href="{{ $dashboardUrl }}"
           style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">
          Voir mon abonnement
        </a>
      </td>
    </tr>
  </table>

  <p style="margin:0; font-size:13px; line-height:1.6; color:#94a3b8;">
    Vous pouvez suivre votre consommation à tout moment depuis votre tableau de bord.
  </p>
@endsection
