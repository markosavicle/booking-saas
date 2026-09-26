@php
    $shopName = $tenant?->name ?? config('app.name');
    $today = $tenant?->localNow()->dayOfWeek;
    // Mirrors initials() in resources/js/booking.js: "Niš Classic Barbers" → "NC", "Niš" → "NI".
    $initials = function (string $name): string {
        $words = collect(preg_split('/\s+/u', $name))->map(fn (string $word) => preg_replace('/[^\p{L}\p{M}\p{N}]/u', '', $word))->filter()->values();
        $letters = $words->count() === 1
            ? grapheme_substr($words[0], 0, 2)
            : $words->take(2)->map(fn (string $word) => grapheme_substr($word, 0, 1))->join('');

        return mb_strtoupper($letters);
    };

    // Shops fill these in the admin panel; generic copy covers the gaps, never a fake address.
    $tagline = $tenant?->tagline ?: 'Sharp cuts, hot towels and an honest pour. Walk in looking good, walk out looking better.';
    $about = $tenant?->aboutParagraphs() ?: [
        'Low lights, good records and barbers who take their time. Every cut starts with a proper consultation and finishes with a hot towel, a straight-razor neckline and a style that still works on day five.',
        "Grab a coffee or something stronger while you wait. Book online, show up, sit back. We'll handle the rest.",
    ];
    $address = $tenant?->addressLines() ?? [];
    $phone = $tenant?->phone;
    $socials = array_filter(['Instagram' => $tenant?->social_instagram, 'Facebook' => $tenant?->social_facebook]);
    $heroImage = $tenant?->heroImageUrl() ?? asset(\App\Models\Tenant::DEFAULT_HERO_IMAGE);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth scroll-pt-20 bg-ink-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#09090a">
    <title>{{ $tenant ? "{$shopName} · Book your chair" : 'Book your chair · '.config('app.name') }}</title>
    <meta name="description" content="{{ $tenant ? "Book your next cut at {$shopName} in under a minute. No account needed." : 'Book your next cut in under a minute. No account needed.' }}">
    <link rel="preload" as="image" href="{{ $heroImage }}" fetchpriority="high">
    @fonts(['inter', 'playfair-display'])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-full bg-ink-950 font-sans text-ink-300 antialiased selection:bg-gold-400 selection:text-ink-950">

{{-- Navigation --}}
<header class="fixed inset-x-0 top-0 z-40 border-b border-white/5 bg-ink-950/70 pt-[env(safe-area-inset-top)] backdrop-blur-xl">
    <nav class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6" aria-label="Main">
        <a href="#top" class="flex min-w-0 items-center gap-3">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-full border border-gold-500/50 font-display text-sm font-semibold text-gold-300">
                {{ $initials($shopName) }}
            </span>
            <span class="truncate font-display text-lg font-semibold text-white">{{ $shopName }}</span>
        </a>
        <div class="flex items-center gap-1 sm:gap-6">
            <a href="#about" class="hidden text-sm text-ink-300 transition hover:text-gold-300 sm:block">About</a>
            @if ($staff->isNotEmpty())
                <a href="#team" class="hidden text-sm text-ink-300 transition hover:text-gold-300 sm:block">Team</a>
            @endif
            <a href="#book" class="tap rounded-full bg-gradient-to-b from-gold-300 to-gold-500 px-4 py-2 text-xs font-semibold tracking-wider text-ink-950 uppercase">Book</a>
        </div>
    </nav>
</header>

<main id="top">
    {{-- Hero --}}
    <section class="relative isolate flex min-h-[88svh] items-end overflow-hidden pt-16 sm:items-center">
        <img
            src="{{ $heroImage }}"
            alt=""
            class="absolute inset-0 -z-20 size-full object-cover object-[70%_center]"
            fetchpriority="high"
            decoding="async"
        >
        <div class="absolute inset-0 -z-10 bg-gradient-to-t from-ink-950 via-ink-950/70 to-ink-950/20 sm:bg-gradient-to-r sm:from-ink-950 sm:via-ink-950/75 sm:to-transparent" aria-hidden="true"></div>

        <div class="mx-auto w-full max-w-6xl px-4 pb-14 sm:px-6 sm:pb-0">
            <div class="max-w-xl">
                <p class="eyebrow flex items-center gap-3">
                    <span class="h-px w-10 bg-gold-400"></span>
                    Barbershop
                </p>
                <h1 class="mt-5 font-display text-5xl leading-[1.05] font-semibold text-white sm:text-7xl">
                    {{ $shopName }}
                </h1>
                <p class="mt-5 max-w-md text-base leading-relaxed text-ink-300 sm:text-lg">{{ $tagline }}</p>

                <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                    <a href="#book" class="btn-gold tap px-8">
                        Book appointment
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .75.75v10.64l3.72-3.72a.75.75 0 1 1 1.06 1.06l-5 5a.75.75 0 0 1-1.06 0l-5-5a.75.75 0 1 1 1.06-1.06l3.72 3.72V3.75A.75.75 0 0 1 10 3Z" clip-rule="evenodd"/></svg>
                    </a>
                    <a href="#about" class="btn-ghost tap border-white/15 bg-ink-950/30 backdrop-blur">Hours &amp; location</a>
                </div>

                <ul class="mt-10 flex flex-wrap gap-x-6 gap-y-2 text-xs font-medium tracking-wide text-ink-400">
                    @foreach (['No account needed', 'SMS confirmation', 'Free cancellation'] as $perk)
                        <li class="flex items-center gap-2">
                            <svg class="size-3.5 text-gold-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4l3.3 3.29 7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            {{ $perk }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- About & location --}}
    <section id="about" class="mx-auto grid max-w-6xl gap-12 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-[1.1fr_1fr] lg:gap-20">
        <div>
            <p class="eyebrow">The shop</p>
            <h2 class="section-title mt-3">Old-school craft, <em class="gold-text italic">modern</em> chair.</h2>
            <div class="mt-6 space-y-4 text-base leading-relaxed text-ink-400">
                @foreach ($about as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>

            @if ($address || $phone)
            <dl class="mt-10 grid gap-6 sm:grid-cols-2">
                @if ($address)
                <div class="flex gap-4">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full border border-ink-700 text-gold-300">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                    </span>
                    <div>
                        <dt class="text-sm font-semibold text-white">Find us</dt>
                        <dd class="mt-1 text-sm leading-relaxed text-ink-400">
                            @foreach ($address as $line)
                                {{ $line }}@unless ($loop->last)<br>@endunless
                            @endforeach
                        </dd>
                        <dd class="mt-2">
                            <a href="{{ $tenant->mapsUrl() }}" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold tracking-wide text-gold-300 hover:text-gold-200">Get directions &rarr;</a>
                        </dd>
                    </div>
                </div>
                @endif
                @if ($phone)
                <div class="flex gap-4">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full border border-ink-700 text-gold-300">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                    </span>
                    <div>
                        <dt class="text-sm font-semibold text-white">Call</dt>
                        <dd class="mt-1 text-sm tabular-nums"><a href="{{ $tenant->phoneHref() }}" class="text-ink-400 hover:text-gold-300">{{ $phone }}</a></dd>
                    </div>
                </div>
                @endif
            </dl>
            @endif
        </div>

        @if ($tenant)
            <div class="card self-start p-6 sm:p-8">
                <div class="flex items-center justify-between gap-4">
                    <h3 class="font-display text-2xl font-semibold text-white">Opening hours</h3>
                    <span class="text-xs text-ink-400">{{ str_replace('_', ' ', $tenant->timezone) }}</span>
                </div>
                <ul class="mt-6 divide-y divide-ink-700/70">
                    @foreach ($tenant->weeklyHours() as $day => $hours)
                        <li @class([
                            'flex items-center justify-between gap-4 py-3 text-sm',
                            'text-gold-200' => $day === $today,
                        ])>
                            <span class="flex items-center gap-2 {{ $day === $today ? 'font-semibold' : 'text-ink-300' }}">
                                {{ \Carbon\Carbon::getDays()[$day] }}
                                @if ($day === $today)
                                    <span class="rounded-full bg-gold-400/15 px-2 py-0.5 text-[0.6rem] font-semibold tracking-widest text-gold-300 uppercase">Today</span>
                                @endif
                            </span>
                            @if ($hours)
                                <span class="tabular-nums {{ $day === $today ? '' : 'text-white' }}">{{ substr($hours->opens_at, 0, 5) }} – {{ substr($hours->closes_at, 0, 5) }}</span>
                            @else
                                <span class="text-ink-600">Closed</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <a href="#book" class="btn-gold tap mt-8 w-full">Book a chair</a>
            </div>
        @endif
    </section>

    {{-- Booking --}}
    <section id="book" class="relative border-y border-ink-800 bg-ink-900/40 py-16 sm:py-24">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-[radial-gradient(ellipse_at_top,rgba(214,178,94,0.12),transparent_65%)]" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-6xl px-4 text-center sm:px-6">
            <p class="eyebrow">Online booking</p>
            <h2 class="section-title mt-3">Reserve your <em class="gold-text italic">chair</em></h2>
            <p class="mx-auto mt-4 max-w-md text-sm text-ink-400">Four quick steps. We text you a code to confirm, so there's no account or password.</p>
        </div>

        <div
            class="relative mx-auto mt-10 min-h-[36rem] w-full max-w-2xl scroll-mt-20 border-y border-ink-700 bg-ink-950/90 px-4 pt-5 pb-8 shadow-2xl shadow-black/50 sm:rounded-3xl sm:border sm:px-8 sm:pt-8 sm:pb-10"
            x-data="bookingWidget(@js($initialSlug))"
        >
            <div x-cloak>
                {{-- Widget header --}}
                <div class="mb-7">
                    <div class="flex items-center justify-between gap-4">
                        <button
                            type="button"
                            class="tap -ml-2 flex size-10 items-center justify-center rounded-full text-ink-400 hover:bg-ink-800 hover:text-gold-300"
                            x-show="['time', 'details', 'verify'].includes(step)"
                            @click="back()"
                            aria-label="Back"
                        >
                            <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.8 4.2a.75.75 0 0 1 0 1.06L8.06 10l4.74 4.74a.75.75 0 1 1-1.06 1.06l-5.27-5.27a.75.75 0 0 1 0-1.06l5.27-5.27a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
                        </button>
                        <p class="eyebrow flex-1 text-center">
                            <span x-text="tenant ? tenant.name : 'Book your chair'"></span>
                        </p>
                        <span class="size-10" x-show="['time', 'details', 'verify'].includes(step)" aria-hidden="true"></span>
                    </div>

                    <h3 class="mt-2 text-center font-display text-3xl leading-tight font-semibold text-white sm:text-4xl">
                        <span x-show="step === 'service'">Choose your <em class="gold-text italic">cut</em></span>
                        <span x-show="step === 'time'">Pick a <em class="gold-text italic">time</em></span>
                        <span x-show="step === 'details'">Your <em class="gold-text italic">details</em></span>
                        <span x-show="step === 'verify'">Confirm by <em class="gold-text italic">SMS</em></span>
                        <span x-show="step === 'done'">You're <em class="gold-text italic">booked</em></span>
                    </h3>

                    {{-- Progress --}}
                    <ol class="mt-7 grid grid-cols-4 gap-2" x-show="step !== 'done'" aria-label="Booking progress">
                        <template x-for="(item, index) in steps" :key="item.key">
                            <li :aria-current="index === stepIndex ? 'step' : null">
                                <div
                                    class="h-1 rounded-full transition-colors duration-500"
                                    :class="index <= stepIndex ? 'bg-gradient-to-r from-gold-300 to-gold-500' : 'bg-ink-700'"
                                ></div>
                                <p
                                    class="mt-2 text-[0.65rem] font-semibold tracking-[0.2em] uppercase transition-colors"
                                    :class="index === stepIndex ? 'text-gold-300' : (index < stepIndex ? 'text-ink-300' : 'text-ink-600')"
                                >
                                    <span x-text="`0${index + 1}`"></span>
                                    <span class="hidden sm:inline" x-text="`· ${item.label}`"></span>
                                </p>
                            </li>
                        </template>
                    </ol>
                </div>

                {{-- Messages --}}
                <div
                    class="mb-5 flex items-start gap-3 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-200"
                    x-show="error" x-transition role="alert"
                >
                    <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-8-5a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 10 5Zm0 10a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd"/></svg>
                    <span x-text="error"></span>
                </div>
                <div
                    class="mb-5 rounded-xl border border-gold-500/30 bg-gold-500/10 px-4 py-3 text-sm text-gold-200"
                    x-show="notice" x-transition role="status" x-text="notice"
                ></div>

                {{-- Step 1: shop & service --}}
                <div x-show="step === 'service'" x-transition.opacity.duration.300ms>
                    {{-- Shop picker --}}
                    <div x-show="!tenant">
                        <p class="mb-4 text-center text-sm text-ink-400">Select a shop to see its services.</p>

                        <div class="grid gap-3 sm:grid-cols-2" x-show="tenants.length">
                            <template x-for="shop in tenants" :key="shop.id">
                                <button type="button" class="card-interactive tap group flex items-center gap-3 p-4 text-left sm:p-5" @click="selectTenant(shop.slug)" :disabled="loading" :title="shop.name">
                                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full border border-gold-500/50 font-display text-lg text-gold-300" x-text="initials(shop.name)"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="line-clamp-2 font-display text-lg leading-snug font-semibold text-balance break-words text-white" x-text="shop.name"></span>
                                        <span class="mt-0.5 block text-xs tracking-wide text-ink-400">View services</span>
                                    </span>
                                    <svg class="size-5 shrink-0 text-ink-600 transition group-hover:translate-x-0.5 group-hover:text-gold-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 15.8a.75.75 0 0 1 0-1.06L11.94 10 7.2 5.26a.75.75 0 1 1 1.06-1.06l5.27 5.27a.75.75 0 0 1 0 1.06l-5.27 5.27a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
                                </button>
                            </template>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2" x-show="loading && !tenants.length">
                            <div class="card h-[5.5rem] animate-pulse"></div>
                            <div class="card h-[5.5rem] animate-pulse"></div>
                        </div>

                        <p class="card p-8 text-center text-sm text-ink-400" x-show="!loading && !tenants.length && !error">No shops are taking bookings yet.</p>
                    </div>

                    {{-- Service picker --}}
                    <div x-show="tenant">
                        <div class="mb-6 flex items-center justify-between gap-3 border-b border-ink-700 pb-4" x-show="tenants.length > 1 && !tenantLocked">
                            <p class="text-sm text-ink-400">
                                At <span class="font-medium text-white" x-text="tenant?.name"></span>
                            </p>
                            <button type="button" class="text-xs font-semibold tracking-[0.2em] text-gold-400 uppercase hover:text-gold-200" @click="changeTenant()">Change shop</button>
                        </div>

                        <div class="grid gap-3">
                            <template x-for="item in tenant?.services ?? []" :key="item.id">
                                <button type="button" class="card-interactive tap group flex items-center gap-4 p-5 text-left" @click="selectService(item)">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-display text-xl font-semibold text-white" x-text="item.name"></span>
                                        <span class="mt-1 flex items-center gap-1.5 text-xs tracking-wide text-ink-400">
                                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .2.08.39.22.53l3 3a.75.75 0 1 0 1.06-1.06l-2.78-2.78V5Z" clip-rule="evenodd"/></svg>
                                            <span x-text="`${item.duration_minutes} min`"></span>
                                        </span>
                                    </span>
                                    <span class="font-display text-2xl font-semibold text-gold-300" x-text="price(item.price)"></span>
                                    <svg class="size-5 shrink-0 text-ink-600 transition group-hover:translate-x-0.5 group-hover:text-gold-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 15.8a.75.75 0 0 1 0-1.06L11.94 10 7.2 5.26a.75.75 0 1 1 1.06-1.06l5.27 5.27a.75.75 0 0 1 0 1.06l-5.27 5.27a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
                                </button>
                            </template>
                        </div>

                        <p class="card p-8 text-center text-sm text-ink-400" x-show="tenant && !tenant.services.length">This shop has no bookable services right now.</p>
                    </div>
                </div>

                {{-- Step 2: barber, date & time --}}
                <div x-show="step === 'time'" x-transition.opacity.duration.300ms>
                    <div class="mb-7 flex items-center justify-between gap-3 rounded-2xl bg-ink-800/60 px-4 py-3.5" x-show="service">
                        <div class="min-w-0">
                            <p class="truncate font-display text-lg font-semibold text-white" x-text="service?.name"></p>
                            <p class="text-xs text-ink-400" x-text="service ? `${service.duration_minutes} min` : ''"></p>
                        </div>
                        <span class="font-display text-xl font-semibold text-gold-300" x-text="service ? price(service.price) : ''"></span>
                    </div>

                    {{-- Barber: avatar rail --}}
                    <div class="mb-8" x-show="staffForService.length > 1">
                        <p class="eyebrow mb-3">Barber</p>
                        <div class="rail -mx-4 flex snap-x gap-3 overflow-x-auto overscroll-x-contain px-4 pb-2 sm:-mx-8 sm:px-8" x-drag-scroll>
                            <button
                                type="button"
                                class="tap group flex w-[4.5rem] shrink-0 snap-start flex-col items-center gap-2"
                                :aria-pressed="staffId === null"
                                @click="selectStaff(null)"
                            >
                                <span
                                    class="flex size-16 items-center justify-center rounded-full border-2 transition"
                                    :class="staffId === null ? 'border-gold-400 bg-gold-400/15 text-gold-200 shadow-lg shadow-gold-500/20' : 'border-ink-700 bg-ink-800 text-ink-400 group-hover:border-gold-500/60'"
                                >
                                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                                </span>
                                <span class="w-full truncate text-center text-xs font-medium" :class="staffId === null ? 'text-gold-200' : 'text-ink-300'">Anyone</span>
                            </button>
                            <template x-for="member in staffForService" :key="member.id">
                                <button
                                    type="button"
                                    class="tap group flex w-[4.5rem] shrink-0 snap-start flex-col items-center gap-2"
                                    :aria-pressed="staffId === member.id"
                                    @click="selectStaff(member.id)"
                                >
                                    <span
                                        class="flex size-16 items-center justify-center rounded-full border-2 font-display text-lg font-semibold transition"
                                        :class="staffId === member.id ? 'border-gold-400 bg-gradient-to-b from-gold-300 to-gold-500 text-ink-950 shadow-lg shadow-gold-500/25' : 'border-ink-700 bg-ink-800 text-gold-300 group-hover:border-gold-500/60'"
                                        x-text="initials(member.name)"
                                    ></span>
                                    <span class="w-full truncate text-center text-xs font-medium" :class="staffId === member.id ? 'text-gold-200' : 'text-ink-300'" x-text="member.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Date: swipeable day strip --}}
                    <div class="mb-3 flex items-baseline justify-between gap-3">
                        <p class="eyebrow">Date</p>
                        <p class="text-xs text-ink-400" x-text="selectedDay?.long"></p>
                    </div>
                    <div class="relative -mx-4 sm:-mx-8">
                        <div class="rail flex snap-x snap-mandatory scroll-px-4 gap-2 overflow-x-auto overscroll-x-contain px-4 pb-2 sm:scroll-px-8 sm:px-8" x-drag-scroll>
                            <template x-for="day in days" :key="day.iso">
                                <button
                                    type="button"
                                    class="chip flex w-[3.75rem] shrink-0 snap-start flex-col items-center py-3"
                                    :disabled="!day.open"
                                    :aria-pressed="date === day.iso"
                                    :aria-label="day.long"
                                    @click="selectDate(day.iso)"
                                >
                                    <span class="text-[0.6rem] font-semibold tracking-wider uppercase opacity-70" x-text="day.weekday"></span>
                                    <span class="mt-1 font-display text-xl leading-none font-semibold" x-text="day.day"></span>
                                    <span class="mt-1.5 text-[0.55rem] tracking-wider uppercase opacity-60" x-text="day.month"></span>
                                </button>
                            </template>
                        </div>
                        <div class="pointer-events-none absolute inset-y-0 right-0 w-8 bg-gradient-to-l from-ink-950 to-transparent" aria-hidden="true"></div>
                    </div>

                    {{-- Time: chips grouped by time of day --}}
                    <div class="mt-8 space-y-6">
                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4" x-show="slotsLoading">
                            <template x-for="i in 8" :key="i">
                                <div class="h-12 animate-pulse rounded-2xl bg-ink-800"></div>
                            </template>
                        </div>

                        <template x-for="group in slotGroups" :key="group.key">
                            <div x-show="!slotsLoading">
                                <div class="mb-3 flex items-center gap-3">
                                    <p class="eyebrow" x-text="group.label"></p>
                                    <span class="h-px flex-1 bg-ink-800"></span>
                                    <span class="text-[0.65rem] text-ink-400" x-text="`${group.slots.length} open`"></span>
                                </div>
                                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                    <template x-for="item in group.slots" :key="item.start_time">
                                        <button
                                            type="button"
                                            class="chip h-12 text-center text-[0.95rem] font-semibold tabular-nums"
                                            :aria-pressed="slot?.start_time === item.start_time"
                                            @click="selectSlot(item)"
                                            x-text="time(item.start_time)"
                                        ></button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div class="rounded-2xl border border-dashed border-ink-700 p-8 text-center" x-show="!slotsLoading && !availableSlots.length">
                            <p class="font-display text-lg text-white" x-text="selectedDay?.open ? 'Fully booked' : 'Closed'"></p>
                            <p class="mt-1 text-sm text-ink-400">Please pick another day.</p>
                        </div>
                    </div>
                </div>

                {{-- Step 3: details --}}
                <div x-show="step === 'details'" x-transition.opacity.duration.300ms>
                    <template x-if="slot">
                        <div class="card mb-6 divide-y divide-ink-700">
                            <div class="flex items-center justify-between gap-3 px-5 py-4">
                                <p class="font-display text-lg font-semibold text-white" x-text="service.name"></p>
                                <span class="font-display text-xl font-semibold text-gold-300" x-text="price(service.price)"></span>
                            </div>
                            <div class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                                <span x-text="longDate(slot.start_time)"></span>
                                <span class="font-semibold tabular-nums text-white" x-text="`${time(slot.start_time)} – ${time(slot.end_time)}`"></span>
                            </div>
                            <div class="px-5 py-3 text-sm" x-show="staffId">
                                With <span class="text-white" x-text="staffName(staffId)"></span>
                            </div>
                        </div>
                    </template>

                    <form class="space-y-5" @submit.prevent="requestCode()" novalidate>
                        <div>
                            <label for="name" class="eyebrow mb-2 block">Name</label>
                            <input id="name" type="text" class="field" x-model="form.name" autocomplete="name" required maxlength="100" placeholder="Your name">
                            <p class="mt-1.5 text-xs text-red-300" x-show="fieldErrors.name" x-text="fieldErrors.name?.[0]"></p>
                        </div>

                        <div>
                            <label for="phone" class="eyebrow mb-2 block">Mobile number</label>
                            <input id="phone" type="tel" class="field tabular-nums" x-model="form.phone" autocomplete="tel" inputmode="tel" required placeholder="+381 60 123 4567">
                            <p class="mt-1.5 text-xs text-red-300" x-show="fieldErrors.phone" x-text="fieldErrors.phone?.[0]"></p>
                            <p class="mt-1.5 text-xs text-ink-400" x-show="!fieldErrors.phone">Include the country code. We'll text you a 6-digit code to confirm.</p>
                        </div>

                        <div>
                            <label for="email" class="eyebrow mb-2 block">Email <span class="font-normal tracking-normal text-ink-400 normal-case">— optional</span></label>
                            <input id="email" type="email" class="field" x-model="form.email" autocomplete="email" inputmode="email" placeholder="you@example.com">
                            <p class="mt-1.5 text-xs text-red-300" x-show="fieldErrors.email" x-text="fieldErrors.email?.[0]"></p>
                            <p class="mt-1.5 text-xs text-ink-400" x-show="!fieldErrors.email">For an emailed confirmation.</p>
                        </div>

                        <button type="submit" class="btn-gold tap w-full" :disabled="loading">
                            <span x-show="!loading">Send code</span>
                            <span x-show="loading">Sending…</span>
                        </button>
                    </form>
                </div>

                {{-- Step 4: SMS code --}}
                <div x-show="step === 'verify'" x-transition.opacity.duration.300ms>
                    <div class="card px-5 py-8 text-center sm:px-10">
                        <span class="mx-auto flex size-14 items-center justify-center rounded-full border border-gold-500/50 text-gold-300">
                            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"/></svg>
                        </span>
                        <p class="mt-5 text-sm text-ink-400">We sent a 6-digit code to</p>
                        <p class="mt-1 font-medium tabular-nums text-white" x-text="verification?.phone"></p>

                        <form class="mt-7" @submit.prevent="confirmCode()">
                            <label for="code" class="sr-only">Verification code</label>
                            <input
                                id="code"
                                x-ref="code"
                                type="text"
                                class="field text-center font-display text-3xl tracking-[0.5em] tabular-nums"
                                x-model="code"
                                @input="onCodeInput()"
                                autocomplete="one-time-code"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                placeholder="••••••"
                            >
                            <button type="submit" class="btn-gold tap mt-5 w-full" :disabled="loading || code.length !== 6">
                                <span x-show="!loading">Confirm booking</span>
                                <span x-show="loading">Confirming…</span>
                            </button>
                        </form>

                        <div class="mt-6 flex flex-col items-center gap-2 text-sm">
                            <button type="button" class="text-gold-400 hover:text-gold-200 disabled:text-ink-600" :disabled="resendIn > 0 || loading" @click="requestCode()">
                                <span x-show="resendIn === 0">Resend code</span>
                                <span x-show="resendIn > 0" x-text="`Resend code in ${resendIn}s`"></span>
                            </button>
                            <button type="button" class="text-ink-400 hover:text-white" @click="go('details')">Wrong number? Edit details</button>
                        </div>
                    </div>
                    <p class="mt-4 text-center text-xs text-ink-400">Your booking is only made once the code is confirmed.</p>
                </div>

                {{-- Done --}}
                <div x-show="step === 'done'" x-transition.opacity.duration.300ms>
                    <template x-if="booking">
                        <div>
                            <div class="card overflow-hidden">
                                <div class="border-b border-ink-700 bg-gradient-to-b from-gold-500/10 to-transparent px-5 py-8 text-center">
                                    <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-gradient-to-b from-gold-300 to-gold-500 text-ink-950">
                                        <svg class="size-7" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4l3.3 3.29 7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                                    </span>
                                    <p class="mt-4 font-display text-2xl font-semibold text-white" x-text="booking.tenant.name"></p>
                                    <p class="mt-1 text-sm text-ink-400">We've texted your confirmation<span x-show="form.email"> and emailed it</span>.</p>
                                </div>
                                <dl class="divide-y divide-ink-700 text-sm">
                                    <div class="flex justify-between gap-4 px-5 py-3.5">
                                        <dt class="text-ink-400">Service</dt>
                                        <dd class="text-right font-medium text-white" x-text="booking.service.name"></dd>
                                    </div>
                                    <div class="flex justify-between gap-4 px-5 py-3.5">
                                        <dt class="text-ink-400">Date</dt>
                                        <dd class="text-right font-medium text-white" x-text="longDate(booking.start_time)"></dd>
                                    </div>
                                    <div class="flex justify-between gap-4 px-5 py-3.5">
                                        <dt class="text-ink-400">Time</dt>
                                        <dd class="text-right font-medium tabular-nums text-white" x-text="`${time(booking.start_time)} – ${time(booking.end_time)}`"></dd>
                                    </div>
                                    <div class="flex justify-between gap-4 px-5 py-3.5" x-show="booking.staff_member">
                                        <dt class="text-ink-400">Barber</dt>
                                        <dd class="text-right font-medium text-white" x-text="booking.staff_member?.name"></dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                <button type="button" class="btn-gold tap" @click="restart()">Book another</button>
                                <a class="btn-ghost tap" :href="cancelUrl">Cancel this booking</a>
                            </div>
                            <p class="mt-4 text-center text-xs text-ink-400">The cancel link is also in your SMS, so you can cancel any time before the appointment.</p>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </section>

    {{-- Team --}}
    @if ($staff->isNotEmpty())
        <section id="team" class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
            <div class="text-center">
                <p class="eyebrow">{{ $tenant ? 'The team' : 'Meet the barbers' }}</p>
                <h2 class="section-title mt-3">Hands you can <em class="gold-text italic">trust</em></h2>
            </div>

            <ul class="mt-12 grid grid-cols-2 gap-x-4 gap-y-10 sm:grid-cols-3 lg:grid-cols-4">
                @foreach ($staff as $member)
                    <li class="group relative text-center">
                        <div class="mx-auto flex size-28 items-center justify-center rounded-full bg-gradient-to-b from-gold-300 to-gold-600 p-[2px] transition duration-300 group-hover:shadow-xl group-hover:shadow-gold-500/20 sm:size-32">
                            <span class="flex size-full items-center justify-center rounded-full bg-ink-900 font-display text-3xl font-semibold text-gold-300">
                                {{ $initials($member->name) }}
                            </span>
                        </div>
                        @if ($tenant)
                            <p class="mt-5 font-display text-xl font-semibold text-white">{{ $member->name }}</p>
                            <p class="mt-1 text-xs font-semibold tracking-[0.2em] text-ink-400 uppercase">Barber</p>
                        @else
                            <p class="mt-5 font-display text-xl font-semibold text-white">
                                {{-- Stretched link: the whole card opens the barber's shop. --}}
                                <a href="{{ route('booking', $member->tenant) }}" class="after:absolute after:-inset-2 after:rounded-2xl focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-gold-400">{{ $member->name }}</a>
                            </p>
                            <p class="mt-1 text-xs font-semibold tracking-[0.2em] text-ink-400 uppercase">
                                Barber <span class="text-gold-400">@</span> <span class="transition group-hover:text-gold-300">{{ $member->tenant->name }}</span>
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-14 text-center">
                <a href="#book" class="btn-ghost tap">{{ $tenant ? 'Book with the team' : 'Pick a shop to book' }}</a>
            </div>
        </section>
    @endif
</main>

{{-- Footer --}}
<footer class="border-t border-ink-800 bg-ink-950 pb-[env(safe-area-inset-bottom)]">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-14 sm:grid-cols-3 sm:px-6">
        <div>
            <p class="font-display text-xl font-semibold text-white">{{ $shopName }}</p>
            <p class="mt-3 max-w-xs text-sm leading-relaxed text-ink-400">Classic cuts, hot towel shaves and beard work.</p>
        </div>
        @if ($address || $phone)
        <div>
            <p class="eyebrow">Visit</p>
            <address class="mt-3 text-sm leading-relaxed text-ink-400 not-italic">
                @foreach ($address as $line)
                    {{ $line }}<br>
                @endforeach
                @if ($phone)
                    <a href="{{ $tenant->phoneHref() }}" class="tabular-nums hover:text-gold-300">{{ $phone }}</a>
                @endif
            </address>
        </div>
        @endif
        @if ($socials)
        <div>
            <p class="eyebrow">Follow</p>
            <div class="mt-3 flex gap-3">
                @isset ($socials['Instagram'])
                <a href="{{ $socials['Instagram'] }}" target="_blank" rel="noopener noreferrer" class="tap flex size-10 items-center justify-center rounded-full border border-ink-700 text-ink-400 hover:border-gold-500/60 hover:text-gold-300" aria-label="{{ $shopName }} on Instagram">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.8" fill="currentColor" stroke="none"/></svg>
                </a>
                @endisset
                @isset ($socials['Facebook'])
                <a href="{{ $socials['Facebook'] }}" target="_blank" rel="noopener noreferrer" class="tap flex size-10 items-center justify-center rounded-full border border-ink-700 text-ink-400 hover:border-gold-500/60 hover:text-gold-300" aria-label="{{ $shopName }} on Facebook">
                    <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-7.5h2.5l.5-3h-3V8.75c0-.87.28-1.5 1.55-1.5H16.6V4.6a20 20 0 0 0-2.3-.1c-2.3 0-3.8 1.4-3.8 3.9v2.1H8v3h2.5V21h3Z"/></svg>
                </a>
                @endisset
            </div>
        </div>
        @endif
    </div>
    <div class="border-t border-ink-800">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-4 py-6 text-xs text-ink-600 sm:flex-row sm:px-6">
            <p>&copy; {{ now()->year }} {{ $shopName }}. All rights reserved.</p>
            @if ($tenant)
                <p>Online booking by {{ config('app.name') }}</p>
            @endif
        </div>
    </div>
</footer>
</body>
</html>
