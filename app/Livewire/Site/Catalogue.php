<?php

namespace App\Livewire\Site;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Cart;
use App\Support\Schema;
use App\Support\Seo;
use App\Support\SiteContent;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Public catalogue (/produce) and category pages (/produce/category/{slug}) with a session cart.
 * Shows selling prices only — never costs or stock quantities.
 */
class Catalogue extends Component
{
    use WithPagination;

    /** Only the id is kept in component state; the model is loaded on demand. */
    public ?int $categoryId = null;

    #[Url(except: '')]
    public string $search = '';

    /** @param  ProductCategory|null  $category  bound from the route on category pages (untyped so the component also mounts without one) */
    public function mount($category = null): void
    {
        // The route value is a bound model or the raw slug, depending on how Livewire resolves it.
        if (is_string($category) && $category !== '') {
            $category = ProductCategory::where('slug', $category)->firstOrFail();
        }

        $category = $category instanceof ProductCategory ? $category : null;

        abort_if($category && $category->status !== 'active', 404);

        // Old links used ?category=<id>; send them permanently to the clean category URL.
        if (! $category && ctype_digit((string) request('category')) && ($legacy = ProductCategory::find(request('category')))) {
            redirect()->to(route('site.category', $legacy->slug), 301)->throwResponse();
        }

        $this->categoryId = $category?->id;
    }

    protected function category(): ?ProductCategory
    {
        return $this->categoryId ? ProductCategory::find($this->categoryId) : null;
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function add(int $productId): void
    {
        Cart::add(Product::where('status', 'active')->findOrFail($productId));
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

    protected function seo($products, int $page, ?ProductCategory $category): array
    {
        $suffix = $page > 1 ? ' – Page '.$page : '';
        $pager = $page > 1 ? '?page='.$page : '';
        $noindex = $this->search !== '';
        $itemList = Schema::itemList($products->getCollection(), $category?->name ?? 'Village City produce catalogue');

        if ($category) {
            $c = $category;
            $path = 'produce/category/'.$c->slug;

            return Seo::build([
                'title' => ($c->meta_title ?: $c->name.' – Wholesale Prices in Kenya').$suffix,
                'description' => $c->meta_description ?: Seo::excerpt($c->seo_text ?: ($c->description ? $c->description.'. ' : '').'Buy '.$c->name.' at wholesale prices from Village City, delivered fresh across Kenya.'),
                'path' => $path.$pager,
                'image' => $c->products()->where('status', 'active')->get()->map(fn ($p) => $p->imageUrl())->filter()->first(),
                'noindex' => $noindex,
                'schema_type' => 'CollectionPage',
                'schema' => [$itemList],
                'breadcrumbs' => [['Home', Seo::url('/')], ['Produce', Seo::url('produce')], [$c->name, null]],
            ]);
        }

        $config = Seo::pageConfig('produce');

        return Seo::page('produce', [
            'title' => $config['title'].$suffix,
            'path' => 'produce'.$pager,
            'noindex' => $noindex || $config['noindex'],
            'schema' => [$itemList],
            'breadcrumbs' => [['Home', Seo::url('/')], ['Produce', null]],
        ]);
    }

    public function render()
    {
        $category = $this->category();

        $products = Product::with('category')->where('status', 'active')
            ->when($category, fn ($q) => $q->where('product_category_id', $category->id))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%")))
            ->orderBy('name')->paginate(12);

        $cart = Cart::all();
        $lines = Product::whereIn('id', array_keys($cart))->get();

        return view('livewire.site.catalogue', [
            'products' => $products,
            'categories' => ProductCategory::where('status', 'active')->orderBy('name')->get(['id', 'name', 'slug']),
            'cart' => $cart,
            'cartTotal' => $lines->sum(fn ($p) => $p->selling_price * ($cart[$p->id] ?? 0)),
            'category' => $category,
            'heroTitle' => $category?->name ?? SiteContent::get('produce.hero_title'),
            'heroText' => $category ? ($category->seo_text ?: $category->description) : SiteContent::get('produce.hero_text'),
        ])->layout('components.layouts.site', ['seo' => $this->seo($products, $products->currentPage(), $category)]);
    }
}
