<?php

namespace App\Livewire\Site;

use App\Models\Product;
use App\Services\Metrics;
use App\Support\Cart;
use App\Support\Options;
use App\Support\Schema;
use App\Support\Seo;
use Livewire\Component;

/** Public product detail page with its own URL, metadata and Product structured data. */
class ProductPage extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        abort_if($product->status !== 'active', 404);

        $this->product = $product->load('category');
    }

    public function add(): void
    {
        Cart::add($this->product);
        $this->dispatch('cart-updated');
    }

    public function checkout()
    {
        if (! auth()->check()) {
            session(['url.intended' => route('marketplace', ['cart' => 1])]);
            session()->flash('status', 'Log in or create a buyer account to complete your order. Your cart has been saved.');

            return $this->redirectRoute('login', navigate: true);
        }

        return $this->redirectRoute('marketplace', ['cart' => 1], navigate: true);
    }

    public function render()
    {
        $p = $this->product;
        $category = $p->category;
        $inStock = (app(Metrics::class)->stockByProduct()[$p->id] ?? 0) > 0;
        $image = $p->imageUrl();
        $price = Options::money($p->selling_price);

        $description = $p->meta_description ?: Seo::excerpt(
            ($p->description ? rtrim($p->description, '. ').'. ' : '').'Buy '.$p->name.' at '.$price.' per '.$p->unit.' from Village City — quality-graded, delivered fresh across Kenya.',
        );

        $seo = Seo::build([
            'title' => $p->meta_title ?: $p->name.' – Wholesale Prices in Kenya',
            'description' => $description,
            'path' => 'produce/'.$p->slug,
            'image' => $image,
            'image_alt' => $p->name,
            'og_type' => 'product',
            'schema_type' => 'ItemPage',
            'product' => ['price' => number_format((float) $p->selling_price, 2, '.', ''), 'availability' => $inStock ? 'instock' : 'backorder', 'category' => $category?->name],
            'schema' => [Schema::product($p, $inStock, $image)],
            'breadcrumbs' => array_values(array_filter([
                ['Home', Seo::url('/')], ['Produce', Seo::url('produce')],
                $category ? [$category->name, Seo::url('produce/category/'.$category->slug)] : null,
                [$p->name, null],
            ])),
        ]);

        return view('livewire.site.product-page', [
            'inStock' => $inStock,
            'related' => Product::where('status', 'active')->where('product_category_id', $p->product_category_id)->whereKeyNot($p->id)->orderBy('name')->limit(4)->get(),
            'inCart' => Cart::all()[$p->id] ?? null,
            'cartCount' => Cart::count(),
        ])->layout('components.layouts.site', ['seo' => $seo]);
    }
}
