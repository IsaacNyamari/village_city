<?php

namespace App\Livewire\Site;

use App\Models\Faq;
use App\Support\Schema;
use App\Support\Seo;
use App\Support\SiteContent;
use Livewire\Component;

class FaqPage extends Component
{
    public function render()
    {
        $faqs = Faq::published()->get();

        $seo = Seo::page('faq', [
            'schema' => $faqs->isNotEmpty() ? [Schema::faqPage($faqs)] : [],
            'breadcrumbs' => [['Home', Seo::url('/')], ['FAQ', null]],
        ]);

        return view('livewire.site.faq-page', [
            'faqs' => $faqs,
            'heroTitle' => SiteContent::get('faq.hero_title'),
            'heroText' => SiteContent::get('faq.hero_text'),
        ])->layout('components.layouts.site', ['seo' => $seo]);
    }
}
