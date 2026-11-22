<?php

namespace App\Console\Commands;

use App\Models\CollectionCentre;
use App\Models\DailyListing;
use App\Models\HarvestSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PublishDailyListings extends Command
{
    protected $signature = 'listings:publish {--date= : Y-m-d, defaults to today}';

    protected $description = "Open today's availability for every active centre trading today";

    public function handle(): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $count = 0;

        foreach (CollectionCentre::where('status', 'active')->get() as $centre) {
            if (! $centre->isMarketDay($date)) {
                continue;
            }

            $harvests = HarvestSchedule::query()
                ->whereDate('expected_harvest_date', $date)
                ->whereHas('farmer', fn ($q) => $q->where('collection_centre_id', $centre->id))
                ->with('product')->get()->groupBy('product_id');

            foreach ($harvests as $productId => $rows) {
                $listing = DailyListing::firstOrNew(['collection_centre_id' => $centre->id, 'product_id' => $productId, 'listing_date' => $date->toDateString()]);

                if (! $listing->exists) {
                    $listing->unit_price = $rows->first()->product->selling_price;
                    $listing->quantity_available = $rows->sum('expected_quantity');
                    $listing->save();
                    $count++;
                }
            }
        }

        $this->info("Published {$count} listing(s) for {$date->toDateString()}.");

        return self::SUCCESS;
    }
}
