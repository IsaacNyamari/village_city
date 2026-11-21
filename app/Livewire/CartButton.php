<?php

namespace App\Livewire;

use App\Support\Cart;
use Livewire\Attributes\On;
use Livewire\Component;

/** Header cart button with a live count. It is the target of the "fly to cart" animation. */
class CartButton extends Component
{
    public bool $onMarketplace = false;

    public function mount(): void
    {
        $this->onMarketplace = request()->routeIs('marketplace');
    }

    #[On('cart-updated')]
    public function refreshCount(): void
    {
        // Re-render only.
    }

    public function open()
    {
        if (! auth()->check()) {
            session(['url.intended' => route('marketplace')]);
            session()->flash('status', 'Log in or create a buyer account to complete your order. Your cart has been saved.');

            return $this->redirectRoute('login', navigate: true);
        }

        if ($this->onMarketplace) {
            $this->dispatch('open-cart');

            return null;
        }

        return $this->redirectRoute('marketplace', ['cart' => 1], navigate: true);
    }

    public function render()
    {
        return view('livewire.cart-button', ['count' => Cart::count()]);
    }
}
