@extends('emails.layout')

@section('title', 'Reset your password — HabiMate')

@section('preheader', 'Use this link to set a new password for your HabiMate account.')

@section('content')
  <p style="margin:0 0 8px;font-size:18px;font-weight:700;color:#0f172a;">Password reset</p>
  <p style="margin:0 0 24px;color:#475569;">
    We got a request to reset your password. Tap the button below to choose a new one. This link won’t last forever—use it soon.
  </p>

  @include('emails.partials.button', ['url' => $resetLink, 'label' => 'Reset password'])

  <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#64748b;">
    Or copy and paste this URL:
  </p>
  <p style="margin:8px 0 0;word-break:break-all;font-size:12px;color:#2EC4B6;font-weight:600;">
    {{ $resetLink }}
  </p>
  <p style="margin:24px 0 0;font-size:13px;color:#94a3b8;">
    If you didn’t ask for this, you can safely ignore this email—your password stays the same.
  </p>
@endsection

@section('footer_note')
  Security tip: HabiMate will never ask you for your password by email.
@endsection
