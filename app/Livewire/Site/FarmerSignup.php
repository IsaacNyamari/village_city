<?php

namespace App\Livewire\Site;

use App\Models\County;
use App\Models\Farmer;
use App\Models\Product;
use App\Services\Notifier;
use App\Support\Seo;
use App\Support\SiteContent;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Public farmer / supplier registration. Creates a farmer record in "pending" status so the
 * procurement team can review and approve it from the Farmers module.
 */
class FarmerSignup extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $county_id = '';

    public string $village = '';

    public string $farm_size_acres = '';

    public array $product_ids = [];

    public string $notes = '';

    /** Honeypot. */
    public string $website = '';

    public bool $sent = false;

    public function submit(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:9', 'max:20'],
            'email' => ['nullable', 'email', 'max:160'],
            'county_id' => ['required', 'exists:counties,id'],
            'village' => ['nullable', 'string', 'max:120'],
            'farm_size_acres' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'product_ids' => ['array', 'min:1'],
            'product_ids.*' => ['exists:products,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['product_ids.min' => 'Choose at least one commodity you grow.'], ['county_id' => 'county', 'product_ids' => 'commodities']);

        if ($this->website !== '') {
            $this->sent = true;

            return;
        }

        $key = 'farmer-signup:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError('name', 'Too many registrations from this connection. Please try again later.');

            return;
        }

        RateLimiter::hit($key, 3600);

        // The same phone number is only registered once; the team will follow up either way.
        $existing = Farmer::where('phone', $this->phone)->first();

        if (! $existing) {
            $farmer = Farmer::create([
                'name' => $this->name, 'phone' => $this->phone, 'email' => $this->email ?: null, 'county_id' => $this->county_id,
                'village' => $this->village ?: null, 'farm_size_acres' => $this->farm_size_acres ?: null,
                'notes' => trim('Registered via website. '.$this->notes), 'status' => 'pending', 'registered_at' => today(),
            ]);
            $farmer->products()->sync($this->product_ids);

            Notifier::send('farmers.manage', 'New farmer registration', $farmer->name.' registered on the website and awaits approval.', route('farmers.index', ['filters' => ['status' => 'pending']]), 'user-plus');
        }

        $this->reset(['name', 'phone', 'email', 'county_id', 'village', 'farm_size_acres', 'product_ids', 'notes', 'website']);
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.site.farmer-signup', [
            'counties' => County::where('status', 'active')->orderBy('name')->pluck('name', 'id'),
            'products' => Product::where('status', 'active')->orderBy('name')->pluck('name', 'id'),
            'heroTitle' => SiteContent::get('sell.hero_title'),
            'heroText' => SiteContent::get('sell.hero_text'),
            'steps' => SiteContent::lines('sell.steps'),
        ])->layout('components.layouts.site', ['seo' => Seo::page('sell', ['breadcrumbs' => [['Home', Seo::url('/')], ['Sell with us', null]]])]);
    }
}
