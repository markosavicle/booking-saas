<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book an appointment · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-full bg-slate-100 font-sans text-slate-900 antialiased">
<main
    class="mx-auto flex min-h-screen w-full max-w-xl flex-col px-4 py-6 sm:py-12"
    x-data="bookingWidget(@js($initialSlug))"
    x-cloak
>
    <div class="flex flex-1 flex-col overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 sm:flex-none">

        {{-- Header: current step and context --}}
        <header class="border-b border-slate-100 px-5 py-4 sm:px-6">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="-ml-2 rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                    x-show="['service', 'time', 'confirm'].includes(step)"
                    @click="back()"
                    aria-label="Back"
                >
                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.8 4.2a.75.75 0 0 1 0 1.06L8.06 10l4.74 4.74a.75.75 0 1 1-1.06 1.06l-5.27-5.27a.75.75 0 0 1 0-1.06l5.27-5.27a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
                </button>
                <div class="min-w-0">
                    <p class="truncate text-xs font-medium uppercase tracking-wide text-indigo-600"
                       x-text="tenant ? tenant.name : 'Book an appointment'"></p>
                    <h1 class="text-lg font-semibold" x-text="{
                        shop: 'Choose a shop',
                        service: 'Choose a service',
                        time: 'Pick a time',
                        confirm: 'Confirm your booking',
                        done: 'You\'re booked!',
                    }[step]"></h1>
                </div>
            </div>
            <ol class="mt-4 grid grid-cols-4 gap-1.5" aria-hidden="true">
                <template x-for="(name, index) in ['shop', 'service', 'time', 'confirm']" :key="name">
                    <li class="h-1 rounded-full transition-colors"
                        :class="['shop', 'service', 'time', 'confirm', 'done'].indexOf(step) >= index ? 'bg-indigo-600' : 'bg-slate-200'"></li>
                </template>
            </ol>
        </header>

        <div class="flex-1 px-5 py-5 sm:px-6">
            {{-- Error banner --}}
            <div x-show="error" x-transition role="alert"
                 class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 ring-1 ring-red-200" x-text="error"></div>

            {{-- Step 1: shop --}}
            <section x-show="step === 'shop'">
                <div x-show="loading && !tenants.length" class="space-y-3">
                    <template x-for="i in 3"><div class="h-16 animate-pulse rounded-xl bg-slate-100"></div></template>
                </div>
                <p x-show="!loading && !tenants.length" class="py-8 text-center text-sm text-slate-500">No shops are taking bookings yet.</p>
                <ul class="space-y-3">
                    <template x-for="shop in tenants" :key="shop.id">
                        <li>
                            <button type="button" @click="selectTenant(shop.slug)" :disabled="loading"
                                    class="flex w-full items-center justify-between rounded-xl px-4 py-4 text-left ring-1 ring-slate-200 transition hover:bg-slate-50 hover:ring-indigo-300 disabled:opacity-60">
                                <span class="font-medium" x-text="shop.name"></span>
                                <span class="text-xs text-slate-500" x-text="shop.timezone.replace('_', ' ')"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Step 2: service --}}
            <section x-show="step === 'service'">
                <p x-show="tenant && !tenant.services.length" class="py-8 text-center text-sm text-slate-500">This shop has no bookable services right now.</p>
                <ul class="space-y-3">
                    <template x-for="item in tenant?.services ?? []" :key="item.id">
                        <li>
                            <button type="button" @click="selectService(item)"
                                    class="flex w-full items-center justify-between gap-4 rounded-xl px-4 py-4 text-left ring-1 ring-slate-200 transition hover:bg-slate-50 hover:ring-indigo-300">
                                <span>
                                    <span class="block font-medium" x-text="item.name"></span>
                                    <span class="text-sm text-slate-500" x-text="`${item.duration_minutes} min`"></span>
                                </span>
                                <span class="shrink-0 font-semibold tabular-nums" x-text="item.price"></span>
                            </button>
                        </li>
                    </template>
                </ul>
            </section>

            {{-- Step 3: date & time --}}
            <section x-show="step === 'time'" class="space-y-5">
                <div x-show="staffForService.length > 1">
                    <label for="staff" class="mb-1.5 block text-sm font-medium text-slate-700">Staff member</label>
                    <select id="staff" x-model="staffId" @change="loadSlots()"
                            class="w-full rounded-lg border-0 bg-white px-3 py-2.5 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-indigo-600">
                        <option value="">Anyone available</option>
                        <template x-for="member in staffForService" :key="member.id">
                            <option :value="member.id" x-text="member.name"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <p class="mb-2 text-sm font-medium text-slate-700">Date</p>
                    <div class="-mx-5 flex snap-x gap-2 overflow-x-auto px-5 pb-2 sm:-mx-6 sm:px-6">
                        <template x-for="day in days" :key="day.iso">
                            <button type="button" @click="selectDate(day.iso)" :disabled="!day.open"
                                    class="flex w-14 shrink-0 snap-start flex-col items-center rounded-xl py-2 text-sm ring-1 transition disabled:cursor-not-allowed disabled:opacity-40"
                                    :class="date === day.iso ? 'bg-indigo-600 text-white ring-indigo-600' : 'ring-slate-200 hover:ring-indigo-300'"
                                    :aria-pressed="date === day.iso">
                                <span class="text-xs" x-text="day.weekday"></span>
                                <span class="text-lg font-semibold" x-text="day.day"></span>
                                <span class="text-xs" x-text="day.month"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-baseline justify-between gap-2">
                        <p class="text-sm font-medium text-slate-700">Time</p>
                        <p class="text-xs text-slate-500">Times shown in <span x-text="tenant?.timezone"></span></p>
                    </div>
                    <div x-show="slotsLoading" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <template x-for="i in 8"><div class="h-11 animate-pulse rounded-lg bg-slate-100"></div></template>
                    </div>
                    <p x-show="!slotsLoading && selectedDay && !selectedDay.open" class="rounded-lg bg-slate-50 py-6 text-center text-sm text-slate-500">Closed on this day.</p>
                    <p x-show="!slotsLoading && selectedDay?.open && !availableSlots.length" class="rounded-lg bg-slate-50 py-6 text-center text-sm text-slate-500">No free times left. Try another day.</p>
                    <div x-show="!slotsLoading" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        <template x-for="item in availableSlots" :key="item.start_time">
                            <button type="button" @click="selectSlot(item)"
                                    class="rounded-lg py-2.5 text-sm font-medium tabular-nums ring-1 ring-slate-200 transition hover:bg-indigo-50 hover:ring-indigo-400"
                                    x-text="time(item.start_time)"></button>
                        </template>
                    </div>
                </div>
            </section>

            {{-- Step 4: summary, sign-in, confirm --}}
            <section x-show="step === 'confirm'" class="space-y-5">
                <dl class="divide-y divide-slate-100 rounded-xl bg-slate-50 px-4 text-sm" x-show="slot">
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Service</dt><dd class="text-right font-medium" x-text="service?.name"></dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">When</dt><dd class="text-right font-medium" x-text="slot && `${longDate(slot.start_time)}, ${time(slot.start_time)}–${time(slot.end_time)}`"></dd></div>
                    <div class="flex justify-between gap-4 py-3" x-show="staffId"><dt class="text-slate-500">With</dt><dd class="text-right font-medium" x-text="staffName(Number(staffId))"></dd></div>
                    <div class="flex justify-between gap-4 py-3"><dt class="text-slate-500">Price</dt><dd class="text-right font-medium tabular-nums" x-text="service?.price"></dd></div>
                </dl>

                <template x-if="user">
                    <div class="space-y-3">
                        <p class="text-sm text-slate-600">
                            Booking as <span class="font-medium text-slate-900" x-text="user.name"></span>.
                            <button type="button" class="text-indigo-600 hover:underline" @click="logout()">Not you?</button>
                        </p>
                        <button type="button" @click="confirmBooking()" :disabled="loading"
                                class="w-full rounded-xl bg-indigo-600 py-3 font-semibold text-white shadow-sm transition hover:bg-indigo-500 disabled:opacity-60">
                            <span x-text="loading ? 'Booking…' : 'Confirm booking'"></span>
                        </button>
                    </div>
                </template>

                <template x-if="!user">
                    <form class="space-y-4" @submit.prevent="authenticate()">
                        <div class="grid grid-cols-2 rounded-lg bg-slate-100 p-1 text-sm font-medium">
                            <button type="button" class="rounded-md py-1.5" :class="authMode === 'login' && 'bg-white shadow-sm'" @click="authMode = 'login'; fieldErrors = {}">Sign in</button>
                            <button type="button" class="rounded-md py-1.5" :class="authMode === 'register' && 'bg-white shadow-sm'" @click="authMode = 'register'; fieldErrors = {}">Create account</button>
                        </div>

                        @php
                            $fields = [
                                ['name', 'Full name', 'text', 'name', true],
                                ['email', 'Email', 'email', 'email', false],
                                ['phone', 'Mobile (optional, for SMS reminders)', 'tel', 'tel', true],
                                ['password', 'Password', 'password', 'current-password', false],
                                ['password_confirmation', 'Confirm password', 'password', 'new-password', true],
                            ];
                        @endphp
                        @foreach ($fields as [$field, $label, $type, $autocomplete, $registerOnly])
                            <div @if ($registerOnly) x-show="authMode === 'register'" @endif>
                                <label for="{{ $field }}" class="mb-1.5 block text-sm font-medium text-slate-700">{{ $label }}</label>
                                <input id="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}"
                                       x-model="form.{{ $field }}"
                                       @if ($field === 'phone') placeholder="+381601234567" @endif
                                       @if ($registerOnly) :required="authMode === 'register' && '{{ $field }}' !== 'phone'" @else required @endif
                                       class="w-full rounded-lg border-0 px-3 py-2.5 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-indigo-600"
                                       :class="fieldErrors.{{ $field }} && 'ring-red-400'">
                                <p class="mt-1 text-xs text-red-600" x-show="fieldErrors.{{ $field }}" x-text="fieldErrors.{{ $field }}?.[0]"></p>
                            </div>
                        @endforeach

                        <button type="submit" :disabled="loading"
                                class="w-full rounded-xl bg-slate-900 py-3 font-semibold text-white transition hover:bg-slate-700 disabled:opacity-60"
                                x-text="authMode === 'login' ? 'Sign in to continue' : 'Create account & continue'"></button>
                    </form>
                </template>
            </section>

            {{-- Step 5: done --}}
            <section x-show="step === 'done'" class="py-4 text-center">
                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                    <svg class="size-7" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4l3.3 3.29 7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                </div>
                <template x-if="booking">
                    <div>
                        <p class="font-semibold" x-text="`${booking.service.name} at ${booking.tenant.name}`"></p>
                        <p class="mt-1 text-slate-600" x-text="`${longDate(booking.start_time)} at ${time(booking.start_time)}`"></p>
                        <p class="mt-1 text-sm text-slate-500" x-show="booking.staff_member" x-text="`with ${booking.staff_member?.name}`"></p>
                        <p class="mt-4 text-sm text-slate-500">A confirmation is on its way to <span x-text="user?.email"></span>.</p>
                    </div>
                </template>
                <button type="button" @click="restart()"
                        class="mt-6 rounded-xl px-5 py-2.5 text-sm font-semibold text-indigo-600 ring-1 ring-indigo-200 hover:bg-indigo-50">Book another</button>
            </section>
        </div>
    </div>
</main>
</body>
</html>
