@php use App\Models\Setting; @endphp
<div>
    <x-site.page-hero image="site/delivery" eyebrow="Get in touch" :title="$heroTitle" :text="$heroText" />
    <x-site.breadcrumbs :items="[['Home', route('home')], ['Contact', null]]" />

    <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 lg:grid-cols-3 lg:px-8">
        <div class="lg:col-span-2">
            @if ($sent)
                <div class="rounded-2xl border border-forest-200 bg-forest-50 p-8 text-center">
                    <flux:icon.check-circle class="mx-auto size-10 text-forest-600" />
                    <h2 class="mt-3 text-xl font-semibold text-forest-900">Thank you — message received</h2>
                    <p class="mt-1 text-sm text-zinc-600">Our team will get back to you shortly.</p>
                    <flux:button class="mt-5" wire:click="$set('sent', false)">Send another message</flux:button>
                </div>
            @else
                <form wire:submit="send" class="space-y-5 rounded-2xl border border-zinc-200 p-6 sm:p-8">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="name" label="Your name *" autocomplete="name" />
                        <flux:input wire:model="organisation" label="Organisation (optional)" autocomplete="organization" />
                        <flux:input type="email" wire:model="email" label="Email" autocomplete="email" />
                        <flux:input wire:model="phone" label="Phone" autocomplete="tel" />
                    </div>
                    <flux:select wire:model="topic" label="What is this about? *">
                        @foreach ($topics as $value => $label)<flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:textarea wire:model="message" label="Message *" rows="6" />
                    <div class="hidden" aria-hidden="true"><label>Website <input type="text" wire:model="website" tabindex="-1" autocomplete="off"></label></div>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="send">Send message</flux:button>
                </form>
            @endif
        </div>

        <aside class="space-y-4">
            <div class="rounded-2xl border border-zinc-200 p-6">
                <h2 class="font-semibold text-zinc-900">Reach us directly</h2>
                <ul class="mt-3 space-y-3 text-sm text-zinc-600">
                    @if ($v = Setting::get('company_phone'))<li class="flex gap-2"><flux:icon.phone class="size-5 shrink-0 text-forest-600" />{{ $v }}</li>@endif
                    @if ($v = Setting::get('company_email'))<li class="flex gap-2"><flux:icon.envelope class="size-5 shrink-0 text-forest-600" />{{ $v }}</li>@endif
                    @if ($v = Setting::get('company_address'))<li class="flex gap-2"><flux:icon.map-pin class="size-5 shrink-0 text-forest-600" />{{ $v }}</li>@endif
                    @if (! Setting::get('company_phone') && ! Setting::get('company_email') && ! Setting::get('company_address'))
                        <li>Use the form and we will reply as soon as we can.</li>
                    @endif
                </ul>
            </div>
            <div class="rounded-2xl bg-forest-50 p-6 text-sm text-zinc-700">
                <p class="font-semibold text-forest-900">Already a customer?</p>
                <p class="mt-1">Log in to track orders, view invoices and reorder.</p>
                <flux:button class="mt-3" size="sm" :href="route('login')" wire:navigate>Log in</flux:button>
            </div>
        </aside>
    </div>
</div>
