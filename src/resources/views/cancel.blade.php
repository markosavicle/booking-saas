<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-ink-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#09090a">
    {{-- The URL is a bearer credential: keep it out of search engines and Referer headers. --}}
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Your booking · {{ $appointment->tenant->name }}</title>
    @fonts(['inter', 'playfair-display'])
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-full bg-ink-950 font-sans text-ink-300 antialiased">
<div class="pointer-events-none fixed inset-x-0 top-0 h-[28rem] bg-[radial-gradient(ellipse_at_top,rgba(214,178,94,0.14),transparent_65%)]" aria-hidden="true"></div>

<main class="relative mx-auto flex min-h-screen w-full max-w-lg flex-col px-4 pt-10 pb-10 sm:pt-16">
    <p class="eyebrow text-center">{{ $appointment->tenant->name }}</p>
    <h1 class="mt-3 text-center font-display text-4xl leading-tight font-semibold text-white">
        @if ($appointment->status === \App\Enums\AppointmentStatus::Canceled)
            Booking <em class="gold-text italic">canceled</em>
        @else
            Your <em class="gold-text italic">booking</em>
        @endif
    </h1>

    @if (session('canceled'))
        <div class="mt-8 rounded-xl border border-gold-500/30 bg-gold-500/10 px-4 py-3 text-center text-sm text-gold-200" role="status">
            Your appointment has been canceled. We've sent you a confirmation.
        </div>
    @endif

    @if (session('error'))
        <div class="mt-8 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-center text-sm text-red-200" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <div class="card mt-8 overflow-hidden">
        <dl class="divide-y divide-ink-700 text-sm">
            <div class="flex justify-between gap-4 px-5 py-3.5">
                <dt class="text-ink-400">Service</dt>
                <dd class="text-right font-medium text-white">{{ $appointment->service->name }}</dd>
            </div>
            <div class="flex justify-between gap-4 px-5 py-3.5">
                <dt class="text-ink-400">Date</dt>
                <dd class="text-right font-medium text-white">{{ $local->format('l, j F Y') }}</dd>
            </div>
            <div class="flex justify-between gap-4 px-5 py-3.5">
                <dt class="text-ink-400">Time</dt>
                <dd class="text-right font-medium tabular-nums text-white">{{ $local->format('H:i') }} – {{ $appointment->end_time->setTimezone($appointment->tenant->timezone)->format('H:i') }}</dd>
            </div>
            @if ($appointment->staffMember)
                <div class="flex justify-between gap-4 px-5 py-3.5">
                    <dt class="text-ink-400">Barber</dt>
                    <dd class="text-right font-medium text-white">{{ $appointment->staffMember->name }}</dd>
                </div>
            @endif
            <div class="flex justify-between gap-4 px-5 py-3.5">
                <dt class="text-ink-400">Status</dt>
                <dd class="text-right font-medium {{ $appointment->status === \App\Enums\AppointmentStatus::Canceled ? 'text-red-300' : 'text-gold-300' }}">{{ $appointment->status->getLabel() }}</dd>
            </div>
        </dl>
    </div>

    @if ($cancelable)
        <form method="POST" action="{{ route('booking.cancel', $appointment->cancel_token) }}" class="mt-6">
            @csrf
            <button type="submit" class="btn-ghost w-full border-red-500/40 text-red-200 hover:border-red-400 hover:text-red-100">Cancel this booking</button>
        </form>
        <p class="mt-3 text-center text-xs text-ink-400">This frees your slot for someone else. It can't be undone.</p>
    @elseif ($appointment->status !== \App\Enums\AppointmentStatus::Canceled)
        <p class="mt-6 text-center text-sm text-ink-400">This appointment has already started, so it can no longer be canceled online.</p>
    @endif

    <a href="{{ route('booking', $appointment->tenant->slug) }}" class="btn-gold mt-8 w-full">Book a new time</a>
</main>
</body>
</html>
