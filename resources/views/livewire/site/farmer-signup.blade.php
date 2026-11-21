<div>
    <x-site.page-hero image="site/hero-harvest" eyebrow="For farmers & cooperatives" :title="$heroTitle" :text="$heroText" />
    <x-site.breadcrumbs :items="[['Home', route('home')], ['Sell with us', null]]" />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-3 lg:px-8">
        <div class="lg:col-span-2">
            @if ($sent)
                <div class="vc-rise rounded-2xl border border-forest-200 bg-forest-50 p-8 text-center">
                    <svg class="vc-check mx-auto size-12 text-forest-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                    <h2 class="mt-3 text-xl font-semibold text-forest-900">Thank you — we have your registration</h2>
                    <p class="mt-1 text-sm text-zinc-600">Our county team will call you to confirm your details and tell you where to deliver.</p>
                    <flux:button class="mt-5" :href="route('home')" wire:navigate>Back to home</flux:button>
                </div>
            @else
                <form wire:submit="submit" class="space-y-5 rounded-2xl border border-zinc-200 p-6 sm:p-8">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="name" label="Full name *" autocomplete="name" />
                        <flux:input wire:model="phone" label="Phone number *" autocomplete="tel" placeholder="07XX XXX XXX" />
                        <flux:input type="email" wire:model="email" label="Email (optional)" autocomplete="email" />
                        <flux:select wire:model="county_id" label="County *">
                            <flux:select.option value="">— Select —</flux:select.option>
                            @foreach ($counties as $id => $n)<flux:select.option value="{{ $id }}">{{ $n }}</flux:select.option>@endforeach
                        </flux:select>
                        <flux:input wire:model="village" label="Village / location" />
                        <flux:input type="number" step="0.1" min="0" wire:model="farm_size_acres" label="Farm size (acres)" />
                    </div>

                    <flux:field>
                        <flux:label>What do you grow? *</flux:label>
                        <div class="grid max-h-56 grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-zinc-200 p-3 sm:grid-cols-3">
                            @foreach ($products as $id => $n)<flux:checkbox wire:model="product_ids" value="{{ $id }}" :label="$n" />@endforeach
                        </div>
                        <flux:error name="product_ids" />
                    </flux:field>

                    <flux:textarea wire:model="notes" label="Anything else we should know? (harvest months, volumes, group membership)" rows="3" />
                    <div class="hidden" aria-hidden="true"><label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label></div>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="submit">Submit registration</flux:button>
                </form>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="overflow-hidden rounded-2xl border border-zinc-200">
                <img src="{{ asset('images/site/hero-farmer.jpg') }}" alt="" class="h-44 w-full object-cover" loading="lazy">
                <div class="p-6">
                    <h2 class="font-semibold text-zinc-900">What happens next</h2>
                    <ol class="mt-3 space-y-3 text-sm text-zinc-600">
                        @foreach ($steps as $i => $step)
                            <li class="flex gap-3"><span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-forest-100 text-xs font-semibold text-forest-800">{{ $i + 1 }}</span>{{ $step }}</li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </aside>
    </div>
</div>
