{{-- Shop directory: real links to each shop's own page (the widget below only switches in place). --}}
<section id="shops" class="mx-auto max-w-6xl px-4 pb-20 sm:px-6 sm:pb-28">
    <div class="text-center">
        <p class="eyebrow">The shops</p>
        <h2 class="section-title mt-3">Find your <em class="gold-text italic">barber</em></h2>
        <p class="mx-auto mt-4 max-w-md text-sm text-ink-400">Open a shop to see its team, hours and work, or book straight from its page.</p>
    </div>

    <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($shops as $shop)
            @php($city = collect($shop->addressLines())->last())
            <li>
                <a href="{{ route('booking', $shop) }}" class="card-interactive tap group flex h-full flex-col overflow-hidden">
                    <div class="relative aspect-[16/9] overflow-hidden bg-ink-800">
                        <img src="{{ $shop->heroImageUrl() }}" alt="" loading="lazy" decoding="async" class="size-full object-cover opacity-80 transition duration-500 group-hover:scale-105 group-hover:opacity-100">
                        <div class="absolute inset-0 bg-gradient-to-t from-ink-900 via-ink-900/30 to-transparent" aria-hidden="true"></div>
                        @if ($city)
                            <p class="absolute bottom-3 left-4 text-[0.65rem] font-semibold tracking-[0.2em] text-gold-300 uppercase">{{ $city }}</p>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <p class="font-display text-xl leading-snug font-semibold text-balance text-white">{{ $shop->name }}</p>
                        @if ($shop->tagline)
                            <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-ink-400">{{ $shop->tagline }}</p>
                        @endif
                        <p class="mt-auto flex items-center justify-between gap-3 pt-5 text-xs tracking-wide text-ink-400">
                            <span>{{ trans_choice(':count barber|:count barbers', $shop->staff_members_count) }}</span>
                            <span class="flex items-center gap-1 font-semibold tracking-[0.2em] text-gold-400 uppercase transition group-hover:text-gold-200">
                                View shop
                                <svg class="size-4 transition group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 15.8a.75.75 0 0 1 0-1.06L11.94 10 7.2 5.26a.75.75 0 1 1 1.06-1.06l5.27 5.27a.75.75 0 0 1 0 1.06l-5.27 5.27a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
                            </span>
                        </p>
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
</section>
