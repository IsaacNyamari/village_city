@php
    $navigation = config('villagecity.navigation');
    $crumbGroup = null;
    $crumbItem = null;

    foreach ($navigation as $group => $items) {
        foreach ($items as [$label, $routeName]) {
            $prefix = str_contains($routeName, '.') ? \Illuminate\Support\Str::beforeLast($routeName, '.') : $routeName;

            if (request()->routeIs($routeName) || (str_contains($routeName, '.') && request()->routeIs($prefix.'.*'))) {
                $crumbGroup = $group;
                $crumbItem = [$label, $routeName];
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-800 antialiased">
        <flux:sidebar sticky stashable class="dark border-r border-forest-950 bg-forest-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                @foreach ($navigation as $group => $items)
                    @php $visible = collect($items)->filter(fn ($item) => $item[3] === null || auth()->user()->can($item[3])); @endphp

                    @if ($visible->isNotEmpty())
                        <flux:navlist.group :heading="$group" class="grid">
                            @foreach ($visible as [$label, $routeName, $icon])
                                @php $prefix = str_contains($routeName, '.') ? \Illuminate\Support\Str::beforeLast($routeName, '.').'.*' : $routeName; @endphp
                                <flux:navlist.item :icon="$icon" :href="route($routeName)" :current="request()->routeIs($routeName, $prefix)" wire:navigate>{{ $label }}</flux:navlist.item>
                            @endforeach
                        </flux:navlist.group>
                    @endif
                @endforeach
            </flux:navlist>

            <flux:spacer />

            <div class="px-2 pb-1 text-[11px] leading-snug text-forest-300">
                From the farm to the city,<br>within 24 hours.
            </div>
        </flux:sidebar>

        <flux:header class="sticky top-0 z-20 border-b border-zinc-200 bg-white/95 backdrop-blur">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:breadcrumbs class="max-md:hidden">
                <flux:breadcrumbs.item :href="route('dashboard')" icon="home" wire:navigate />
                @if ($crumbItem && $crumbItem[1] !== 'dashboard')
                    <flux:breadcrumbs.item>{{ $crumbGroup }}</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item :href="route($crumbItem[1])" wire:navigate>{{ $crumbItem[0] }}</flux:breadcrumbs.item>
                @elseif (request()->routeIs('settings.*'))
                    <flux:breadcrumbs.item>My Account</flux:breadcrumbs.item>
                @else
                    <flux:breadcrumbs.item>Dashboard</flux:breadcrumbs.item>
                @endif
            </flux:breadcrumbs>

            <flux:spacer />

            <livewire:global-search />
            <flux:button variant="ghost" size="sm" icon="question-mark-circle" :href="route('help.index')" wire:navigate aria-label="Help and documentation" class="max-sm:hidden" />
            @can('marketplace.view')<livewire:cart-button />@endcan
            <livewire:notification-bell />

            <flux:dropdown position="bottom" align="end">
                <flux:profile :name="auth()->user()->name" :initials="auth()->user()->initials()" icon-trailing="chevron-down" class="max-sm:[&_span.truncate]:hidden" />

                <flux:menu class="w-[240px]">
                    <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-forest-100 font-semibold text-forest-800">{{ auth()->user()->initials() }}</span>
                        <div class="grid flex-1 leading-tight">
                            <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                            <span class="truncate text-xs text-zinc-500">{{ auth()->user()->getRoleNames()->first() ?? 'No role assigned' }}</span>
                        </div>
                    </div>

                    <flux:menu.separator />
                    <flux:menu.item :href="route('help.index')" icon="book-open" wire:navigate>Help & documentation</flux:menu.item>
                    <flux:menu.item :href="route('settings.profile')" icon="user" wire:navigate>My profile</flux:menu.item>
                    <flux:menu.item :href="route('settings.password')" icon="key" wire:navigate>Change password</flux:menu.item>
                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        <flux:toast position="top end" />
        @fluxScripts
    </body>
</html>
