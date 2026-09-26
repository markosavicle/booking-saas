{{-- FAQ: native <details> so it works without JS; a shared name keeps one answer open at a time. --}}
<section id="faq" class="border-t border-ink-800 py-20 sm:py-28">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-[1fr_1.6fr] lg:gap-20">
        <div>
            <p class="eyebrow">Good to know</p>
            <h2 class="section-title mt-3">Questions, <em class="gold-text italic">answered</em></h2>
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-ink-400">
                @if ($tenant?->phone)
                    Something else? Call us on <a href="{{ $tenant->phoneHref() }}" class="text-gold-300 tabular-nums hover:text-gold-200">{{ $tenant->phone }}</a>.
                @else
                    Everything about booking, reminders and cancelling in one place.
                @endif
            </p>
        </div>

        <div class="divide-y divide-ink-700/70 border-y border-ink-700/70">
            @foreach ($faqs as $faq)
                <details name="faq" class="group" @if ($loop->first) open @endif>
                    <summary class="tap flex cursor-pointer list-none items-center justify-between gap-6 py-5 text-left font-display text-lg font-semibold text-white transition hover:text-gold-200 focus-visible:text-gold-200 focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                        {{ $faq['question'] }}
                        <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-ink-700 text-gold-300 transition group-open:rotate-45 group-open:border-gold-500/60" aria-hidden="true">
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z"/></svg>
                        </span>
                    </summary>
                    <p class="max-w-2xl pb-6 text-sm leading-relaxed whitespace-pre-line text-ink-400">{{ $faq['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </div>

    @php
        $structuredFaq = [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ])->all(),
        ];
    @endphp
    {{-- JSON_HEX_TAG turns < and > into \u003C/\u003E, so an answer can never close this tag. --}}
    <script type="application/ld+json">{!! json_encode($structuredFaq, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</section>
