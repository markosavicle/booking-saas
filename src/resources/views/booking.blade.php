<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-ink-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#09090a">
    <title>Book your chair · {{ config('app.name') }}</title>
    @fonts(['inter', 'playfair-display'])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-full bg-ink-950 font-sans text-ink-300 antialiased selection:bg-gold-400 selection:text-ink-950">
<div class="pointer-events-none fixed inset-x-0 top-0 h-[28rem] bg-[radial-gradient(ellipse_at_top,rgba(214,178,94,0.14),transparent_65%)]" aria-hidden="true"></div>

<main
    class="relative mx-auto flex min-h-screen w-full max-w-2xl flex-col px-4 pt-6 pb-10 sm:px-6 sm:pt-12"
    x-data="bookingWidget(@js($initialSlug))"
    x-cloak
>
    {{-- Header --}}
    <header class="mb-8">
        <div class="flex items-center justify-between gap-4">
            <button
                type="button"
                class="-ml-2 flex size-10 items-center justify-center rounded-full text-ink-400 transition hover:bg-ink-800 hover:text-gold-300"
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

        <h1 class="mt-3 text-center font-display text-4xl leading-tight font-semibold text-white sm:text-5xl">
            <span x-show="step === 'service'">Choose your <em class="gold-text italic">cut</em></span>
            <span x-show="step === 'time'">Pick a <em class="gold-text italic">time</em></span>
            <span x-show="step === 'details'">Your <em class="gold-text italic">details</em></span>
            <span x-show="step === 'verify'">Confirm by <em class="gold-text italic">SMS</em></span>
            <span x-show="step === 'done'">You're <em class="gold-text italic">booked</em></span>
        </h1>

        {{-- Progress --}}
        <ol class="mt-8 grid grid-cols-4 gap-2" x-show="step !== 'done'" aria-label="Booking progress">
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
    </header>

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

    <div class="flex-1">
        {{-- Step 1: shop & service --}}
        <section x-show="step === 'service'" x-transition.opacity.duration.300ms>
            {{-- Shop picker --}}
            <div x-show="!tenant">
                <p class="mb-4 text-center text-sm text-ink-400">Select a shop to see its services.</p>

                <div class="grid gap-3 sm:grid-cols-2" x-show="tenants.length">
                    <template x-for="shop in tenants" :key="shop.id">
                        <button type="button" class="card-interactive group flex items-center gap-4 p-5 text-left" @click="selectTenant(shop.slug)" :disabled="loading">
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-full border border-gold-500/50 font-display text-lg text-gold-300" x-text="initials(shop.name)"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-display text-lg font-semibold text-white" x-text="shop.name"></span>
                                <span class="mt-0.5 block text-xs tracking-wide text-ink-400">View services</span>
                            </span>
                            <svg class="size-5 text-ink-600 transition group-hover:translate-x-0.5 group-hover:text-gold-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 15.8a.75.75 0 0 1 0-1.06L11.94 10 7.2 5.26a.75.75 0 1 1 1.06-1.06l5.27 5.27a.75.75 0 0 1 0 1.06l-5.27 5.27a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
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
                <div class="mb-6 flex items-center justify-between gap-3 border-b border-ink-700 pb-4" x-show="tenants.length > 1">
                    <p class="text-sm text-ink-400">
                        At <span class="font-medium text-white" x-text="tenant?.name"></span>
                    </p>
                    <button type="button" class="text-xs font-semibold tracking-[0.2em] text-gold-400 uppercase hover:text-gold-200" @click="changeTenant()">Change shop</button>
                </div>

                <div class="grid gap-3">
                    <template x-for="item in tenant?.services ?? []" :key="item.id">
                        <button type="button" class="card-interactive group flex items-center gap-4 p-5 text-left" @click="selectService(item)">
                            <span class="min-w-0 flex-1">
                                <span class="block font-display text-xl font-semibold text-white" x-text="item.name"></span>
                                <span class="mt-1 flex items-center gap-1.5 text-xs tracking-wide text-ink-400">
                                    <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .2.08.39.22.53l3 3a.75.75 0 1 0 1.06-1.06l-2.78-2.78V5Z" clip-rule="evenodd"/></svg>
                                    <span x-text="`${item.duration_minutes} min`"></span>
                                </span>
                            </span>
                            <span class="font-display text-2xl font-semibold text-gold-300" x-text="price(item.price)"></span>
                            <svg class="size-5 text-ink-600 transition group-hover:translate-x-0.5 group-hover:text-gold-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 15.8a.75.75 0 0 1 0-1.06L11.94 10 7.2 5.26a.75.75 0 1 1 1.06-1.06l5.27 5.27a.75.75 0 0 1 0 1.06l-5.27 5.27a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
                        </button>
                    </template>
                </div>

                <p class="card p-8 text-center text-sm text-ink-400" x-show="tenant && !tenant.services.length">This shop has no bookable services right now.</p>
            </div>
        </section>

        {{-- Step 2: barber, date & time --}}
        <section x-show="step === 'time'" x-transition.opacity.duration.300ms>
            <div class="card mb-6 flex items-center justify-between gap-3 px-5 py-4" x-show="service">
                <div class="min-w-0">
                    <p class="truncate font-display text-lg font-semibold text-white" x-text="service?.name"></p>
                    <p class="text-xs text-ink-400" x-text="service ? `${service.duration_minutes} min` : ''"></p>
                </div>
                <span class="font-display text-xl font-semibold text-gold-300" x-text="service ? price(service.price) : ''"></span>
            </div>

            <div class="mb-7" x-show="staffForService.length > 1">
                <p class="eyebrow mb-3">Barber</p>
                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
                    <button
                        type="button"
                        class="shrink-0 rounded-full border px-4 py-2 text-sm font-medium transition"
                        :class="staffId === null ? 'border-gold-400 bg-gold-400 text-ink-950' : 'border-ink-600 text-ink-300 hover:border-gold-500/60'"
                        @click="selectStaff(null)"
                    >Any barber</button>
                    <template x-for="member in staffForService" :key="member.id">
                        <button
                            type="button"
                            class="flex shrink-0 items-center gap-2 rounded-full border py-1.5 pr-4 pl-1.5 text-sm font-medium transition"
                            :class="staffId === member.id ? 'border-gold-400 bg-gold-400 text-ink-950' : 'border-ink-600 text-ink-300 hover:border-gold-500/60'"
                            @click="selectStaff(member.id)"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full text-[0.65rem] font-semibold"
                                :class="staffId === member.id ? 'bg-ink-950 text-gold-300' : 'bg-ink-700 text-gold-300'"
                                x-text="initials(member.name)"
                            ></span>
                            <span x-text="member.name"></span>
                        </button>
                    </template>
                </div>
            </div>

            <p class="eyebrow mb-3">Date</p>
            <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
                <template x-for="day in days" :key="day.iso">
                    <button
                        type="button"
                        class="flex flex-col items-center rounded-xl border py-2.5 transition disabled:cursor-not-allowed"
                        :class="date === day.iso
                            ? 'border-gold-400 bg-gradient-to-b from-gold-300 to-gold-500 text-ink-950 shadow-lg shadow-gold-500/20'
                            : (day.open ? 'border-ink-700 bg-ink-900 text-white hover:border-gold-500/60' : 'border-transparent text-ink-600')"
                        :disabled="!day.open"
                        :aria-pressed="date === day.iso"
                        :aria-label="day.long"
                        @click="selectDate(day.iso)"
                    >
                        <span class="text-[0.6rem] font-semibold tracking-wider uppercase" :class="date === day.iso ? 'text-ink-900' : 'text-ink-400'" x-text="day.weekday"></span>
                        <span class="mt-0.5 font-display text-lg leading-none font-semibold" x-text="day.day"></span>
                        <span class="mt-1 text-[0.55rem] tracking-wider uppercase opacity-70" x-text="day.month"></span>
                    </button>
                </template>
            </div>

            <div class="mt-8">
                <div class="mb-3 flex items-baseline justify-between gap-3">
                    <p class="eyebrow">Time</p>
                    <p class="text-xs text-ink-400" x-text="selectedDay?.long"></p>
                </div>

                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4" x-show="slotsLoading">
                    <template x-for="i in 8" :key="i">
                        <div class="h-12 animate-pulse rounded-xl bg-ink-800"></div>
                    </template>
                </div>

                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4" x-show="!slotsLoading && availableSlots.length">
                    <template x-for="item in availableSlots" :key="item.start_time">
                        <button
                            type="button"
                            class="rounded-xl border border-ink-700 bg-ink-900 py-3 text-center font-medium tabular-nums text-white transition hover:-translate-y-0.5 hover:border-gold-400 hover:text-gold-200"
                            @click="selectSlot(item)"
                            x-text="time(item.start_time)"
                        ></button>
                    </template>
                </div>

                <div class="card p-8 text-center" x-show="!slotsLoading && !availableSlots.length">
                    <p class="font-display text-lg text-white" x-text="selectedDay?.open ? 'Fully booked' : 'Closed'"></p>
                    <p class="mt-1 text-sm text-ink-400">Please pick another day.</p>
                </div>
            </div>
        </section>

        {{-- Step 3: details --}}
        <section x-show="step === 'details'" x-transition.opacity.duration.300ms>
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

                <button type="submit" class="btn-gold w-full" :disabled="loading">
                    <span x-show="!loading">Send code</span>
                    <span x-show="loading">Sending…</span>
                </button>
            </form>
        </section>

        {{-- Step 4: SMS code --}}
        <section x-show="step === 'verify'" x-transition.opacity.duration.300ms>
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
                    <button type="submit" class="btn-gold mt-5 w-full" :disabled="loading || code.length !== 6">
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
        </section>

        {{-- Done --}}
        <section x-show="step === 'done'" x-transition.opacity.duration.300ms>
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
                        <button type="button" class="btn-gold" @click="restart()">Book another</button>
                        <a class="btn-ghost" :href="cancelUrl">Cancel this booking</a>
                    </div>
                    <p class="mt-4 text-center text-xs text-ink-400">The cancel link is also in your SMS, so you can cancel any time before the appointment.</p>
                </div>
            </template>
        </section>
    </div>

    <footer class="mt-12 flex items-center justify-center gap-2 text-[0.65rem] font-semibold tracking-[0.25em] text-ink-600 uppercase">
        <span class="h-px w-8 bg-ink-700"></span>
        No account needed
        <span class="h-px w-8 bg-ink-700"></span>
    </footer>
</main>
</body>
</html>
