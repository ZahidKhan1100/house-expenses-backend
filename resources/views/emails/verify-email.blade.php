@extends('emails.layout')

@section('title', 'Verify your email — HabiMate')

@section('preheader', 'Confirm your email to activate your HabiMate account and sync with your house.')

@section('content')
  <p style="margin:0 0 8px;font-size:18px;font-weight:700;color:#0f172a;">Welcome, {{ $name }}!</p>
  <p style="margin:0 0 24px;color:#475569;">
    You’re one step away from fair splits, receipt scanning, and a house wall that actually feels human. Confirm your email and you’re in.
  </p>

  @include('emails.partials.button', ['url' => $verificationUrl, 'label' => 'Verify email'])

  <p style="margin:24px 0 0;font-size:13px;line-height:1.6;color:#64748b;">
    If the button doesn’t work, paste this link into your browser:
  </p>
  <p style="margin:8px 0 0;word-break:break-all;font-size:12px;color:#2EC4B6;font-weight:600;">
    {{ $verificationUrl }}
  </p>
@endsection

@section('footer_note')
  If you didn’t create a HabiMate account, you can ignore this email—no hard feelings.
@endsection
