{{-- Gallery: the first photo is shown large; any photo opens full size in a lightbox. --}}
<section
    id="gallery"
    class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28"
    x-data="gallery(@js($gallery->map(fn ($image) => ['src' => $image->url(), 'caption' => $image->caption])->values()))"
>
    <div class="text-center">
        <p class="eyebrow">Our work</p>
        <h2 class="section-title mt-3">Fresh from the <em class="gold-text italic">chair</em></h2>
    </div>

    <ul class="mt-12 grid grid-flow-dense grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-4">
        @foreach ($gallery as $image)
            <li @class(['group relative aspect-square overflow-hidden rounded-2xl bg-ink-800', 'col-span-2 row-span-2' => $loop->first])>
                <button type="button" class="tap block size-full focus-visible:outline-none" @click="show({{ $loop->index }})">
                    <img
                        src="{{ $image->url() }}"
                        alt="{{ $image->caption ?? "Photo from {$tenant->name}" }}"
                        loading="lazy"
                        decoding="async"
                        class="size-full object-cover transition duration-500 group-hover:scale-105"
                    >
                    <span class="absolute inset-0 rounded-2xl ring-1 ring-white/5 ring-inset transition group-hover:ring-gold-500/60 group-focus-within:ring-2 group-focus-within:ring-gold-400" aria-hidden="true"></span>
                    @if ($image->caption)
                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-ink-950/90 to-transparent px-3 pt-8 pb-3 text-left text-xs font-medium text-white opacity-0 transition group-hover:opacity-100 max-sm:hidden" aria-hidden="true">{{ $image->caption }}</span>
                    @endif
                </button>
            </li>
        @endforeach
    </ul>

    <dialog
        x-ref="dialog"
        class="m-auto max-h-none max-w-none bg-transparent p-0 backdrop:bg-ink-950/95 backdrop:backdrop-blur-sm"
        aria-label="Photo viewer"
        @close="index = null"
        @click.self="close()"
        @keydown.arrow-right.prevent="step(1)"
        @keydown.arrow-left.prevent="step(-1)"
    >
        <template x-if="current">
            <figure class="flex max-h-[92svh] w-[min(92vw,72rem)] flex-col items-center gap-4">
                <img :src="current.src" :alt="current.caption ?? ''" class="max-h-[80svh] w-auto rounded-2xl object-contain shadow-2xl shadow-black">
                <figcaption class="flex w-full items-center justify-between gap-4 text-sm text-ink-300">
                    <span x-text="current.caption ?? ''"></span>
                    <span class="shrink-0 text-xs tabular-nums text-ink-400" x-text="`${index + 1} / ${images.length}`"></span>
                </figcaption>
            </figure>
        </template>
        <button type="button" class="tap fixed top-4 right-4 flex size-11 items-center justify-center rounded-full border border-ink-700 bg-ink-900/80 text-ink-300 hover:text-gold-300" @click="close()" aria-label="Close">
            <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
        </button>
        <template x-if="images.length > 1">
            <div>
                <button type="button" class="tap fixed top-1/2 left-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-ink-700 bg-ink-900/80 text-ink-300 hover:text-gold-300" @click="step(-1)" aria-label="Previous photo">
                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M12.8 4.2a.75.75 0 0 1 0 1.06L8.06 10l4.74 4.74a.75.75 0 1 1-1.06 1.06l-5.27-5.27a.75.75 0 0 1 0-1.06l5.27-5.27a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd"/></svg>
                </button>
                <button type="button" class="tap fixed top-1/2 right-3 flex size-11 -translate-y-1/2 items-center justify-center rounded-full border border-ink-700 bg-ink-900/80 text-ink-300 hover:text-gold-300" @click="step(1)" aria-label="Next photo">
                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 15.8a.75.75 0 0 1 0-1.06L11.94 10 7.2 5.26a.75.75 0 1 1 1.06-1.06l5.27 5.27a.75.75 0 0 1 0 1.06l-5.27 5.27a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
                </button>
            </div>
        </template>
    </dialog>
</section>
