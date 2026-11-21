<div>
    <x-site.page-hero image="site/market-stall" eyebrow="Help & answers" :title="$heroTitle" :text="$heroText" />
    <x-site.breadcrumbs :items="[['Home', route('home')], ['FAQ', null]]" />

    <section class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
        @if ($faqs->isEmpty())
            <p class="rounded-xl border border-dashed border-zinc-300 p-10 text-center text-sm text-zinc-500">Answers are coming soon. In the meantime, <a href="{{ route('site.contact') }}" class="underline" wire:navigate>send us a message</a>.</p>
        @else
            <div class="divide-y divide-zinc-200 rounded-2xl border border-zinc-200">
                @foreach ($faqs as $faq)
                    <details class="group p-5" id="faq-{{ $faq->id }}">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-left"><h2 class="font-medium text-zinc-900">{{ $faq->question }}</h2><flux:icon.chevron-down variant="micro" class="size-4 shrink-0 text-zinc-500 transition group-open:rotate-180" /></summary>
                        <p class="mt-3 text-sm leading-relaxed text-zinc-600">{!! nl2br(e($faq->answer)) !!}</p>
                    </details>
                @endforeach
            </div>
        @endif

        <div class="mt-10 rounded-2xl bg-forest-50 p-6 text-center">
            <h2 class="text-lg font-semibold text-forest-900">Still have a question?</h2>
            <p class="mt-1 text-sm text-zinc-600">Our team is happy to help with wholesale supply, delivery and payment terms.</p>
            <flux:button class="mt-4" variant="primary" :href="route('site.contact')" wire:navigate>Contact us</flux:button>
        </div>
    </section>
</div>
