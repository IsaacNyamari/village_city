<?php

namespace App\Http\Controllers;

use App\Models\CollectionCentre;
use App\Models\County;
use App\Models\Faq;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Seo;
use App\Support\SiteContent;
use Illuminate\Contracts\View\View;

/** Static public pages (home, about). Dynamic ones are Livewire components in App\Livewire\Site. */
class SiteController extends Controller
{
    public function home(): View
    {
        $hero = SiteContent::image('home.hero_image');

        return view('site.home', [
            'seo' => Seo::page('home', ['preload' => $hero, 'image_alt' => SiteContent::get('home.hero_image_alt')]),
            'stats' => [
                'farmers' => Farmer::where('status', 'active')->count(),
                'counties' => County::where('is_sourcing', true)->where('status', 'active')->count(),
                'products' => Product::where('status', 'active')->count(),
                'centres' => CollectionCentre::where('status', 'active')->count(),
            ],
            'categories' => ProductCategory::where('status', 'active')
                ->with(['products' => fn ($q) => $q->where('status', 'active')->orderBy('name')])
                ->withCount(['products' => fn ($q) => $q->where('status', 'active')])->orderBy('name')->get(),
            'featured' => Product::where('status', 'active')->inRandomOrder()->limit(6)->get(),
            'counties' => County::where('is_sourcing', true)->where('status', 'active')->orderBy('name')->get(),
            'faqs' => Faq::published()->where('show_on_home', true)->limit(6)->get(),
            'heroImage' => $hero,
        ]);
    }

    public function about(): View
    {
        return view('site.about', [
            'seo' => Seo::page('about', ['breadcrumbs' => [['Home', Seo::url('/')], ['About us', null]]]),
        ]);
    }
}
