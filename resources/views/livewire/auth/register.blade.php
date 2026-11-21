<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $phone = '';
    public string $customer_type = 'household';
    public string $business_name = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'phone' => ['required', 'string', 'min:9', 'max:20'],
            'customer_type' => ['required', 'in:'.implode(',', array_keys(\App\Support\Options::CUSTOMER_TYPES))],
            'business_name' => ['nullable', 'string', 'max:160'],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $customerData = ['phone' => $validated['phone'], 'type' => $validated['customer_type'], 'business' => $validated['business_name'] ?? null];
        unset($validated['customer_type'], $validated['business_name']);

        // Public sign-ups are marketplace customers.
        event(new Registered(($user = User::create($validated))));

        if (\Spatie\Permission\Models\Role::where('name', 'Customer')->exists()) {
            $user->assignRole('Customer');
        }

        \App\Models\Customer::create(['user_id' => $user->id, 'name' => $customerData['business'] ?: $user->name, 'contact_person' => $user->name, 'email' => $user->email, 'phone' => $customerData['phone'], 'type' => $customerData['type'], 'payment_terms' => 'cash_on_delivery']);

        Auth::login($user);

        // Honour a saved cart: guests sent here from checkout return to it.
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Create an account" description="Enter your details below to create your account" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <div class="grid gap-2">
            <flux:input wire:model="name" id="name" label="{{ __('Name') }}" type="text" name="name" required autofocus autocomplete="name" placeholder="Full name" />
        </div>

        <!-- Buyer details -->
        <div class="grid gap-2">
            <flux:select wire:model.live="customer_type" label="I am buying as">
                @foreach (\App\Support\Options::CUSTOMER_TYPES as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @if ($customer_type !== 'household')
            <div class="grid gap-2"><flux:input wire:model="business_name" label="Business / organisation name" type="text" autocomplete="organization" /></div>
        @endif
        <div class="grid gap-2"><flux:input wire:model="phone" label="Phone number" type="tel" required autocomplete="tel" placeholder="07XX XXX XXX" /></div>

        <!-- Email Address -->
        <div class="grid gap-2">
            <flux:input wire:model="email" id="email" label="{{ __('Email address') }}" type="email" name="email" required autocomplete="email" placeholder="email@example.com" />
        </div>

        <!-- Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password"
                id="password"
                label="{{ __('Password') }}"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Password"
            />
        </div>

        <!-- Confirm Password -->
        <div class="grid gap-2">
            <flux:input
                wire:model="password_confirmation"
                id="password_confirmation"
                label="{{ __('Confirm password') }}"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Confirm password"
            />
        </div>

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Create account') }}
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        Already have an account?
        <x-text-link href="{{ route('login') }}">Log in</x-text-link>
    </div>
</div>
