<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/** Starter FAQs for the public website. Safe to re-run; only seeds when no FAQs exist. */
class FaqSeeder extends Seeder
{
    public function run(): void
    {
        if (Faq::exists()) {
            return;
        }

        foreach (config('site.starter_faqs') as $i => [$question, $answer, $onHome]) {
            Faq::create(['question' => $question, 'answer' => $answer, 'show_on_home' => $onHome, 'sort_order' => ($i + 1) * 10]);
        }
    }
}
