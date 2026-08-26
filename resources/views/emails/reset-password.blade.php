@extends('emails.layout')

@section('content')
  <h1 style="margin:0 0 16px 0; font-size:20px; font-weight:700; color:#0f172a;">
    Réinitialisation de votre mot de passe
  </h1>

  <p style="margin:0 0 20px 0; font-size:14px; line-height:1.6; color:#475569;">
    Vous recevez cet email car une demande de réinitialisation de mot de passe a été
    effectuée pour votre compte {{ config('app.name') }}.
  </p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px 0;">
    <tr>
      <td style="border-radius:10px; background-color:#4f46e5;">
        <a href="{{ $url }}"
           style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">
          Réinitialiser le mot de passe
        </a>
      </td>
    </tr>
  </table>

  <p style="margin:0 0 8px 0; font-size:13px; line-height:1.6; color:#94a3b8;">
    Ce lien expirera dans {{ $expireMinutes }} minutes.
  </p>
  <p style="margin:0; font-size:13px; line-height:1.6; color:#94a3b8;">
    Si vous n'êtes pas à l'origine de cette demande, aucune action n'est requise —
    votre mot de passe restera inchangé.
  </p>
@endsection
