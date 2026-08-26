@extends('emails.layout')

@section('content')
  <h1 style="margin:0 0 16px 0; font-size:20px; font-weight:700; color:#0f172a;">
    Confirmez votre adresse email
  </h1>

  <p style="margin:0 0 20px 0; font-size:14px; line-height:1.6; color:#475569;">
    Bonjour {{ $name }},<br>
    Merci de vous être inscrit sur {{ config('app.name') }}. Confirmez votre adresse
    email pour activer toutes les fonctionnalités de votre compte.
  </p>

  <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px 0;">
    <tr>
      <td style="border-radius:10px; background-color:#4f46e5;">
        <a href="{{ $url }}"
           style="display:inline-block; padding:12px 28px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">
          Confirmer mon email
        </a>
      </td>
    </tr>
  </table>

  <p style="margin:0; font-size:13px; line-height:1.6; color:#94a3b8;">
    Si vous n'êtes pas à l'origine de cette inscription, vous pouvez ignorer cet email.
  </p>
@endsection
