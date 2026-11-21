<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\CollectionCentre;
use App\Models\County;
use App\Models\Customer;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\ReportBuilder;
use Livewire\Attributes\Url;
use Livewire\Component;

class Reports extends Component
{
    use Page;

    #[Url]
    public string $report = 'procurement';

    #[Url]
    public array $filters = [];

    public function mount(): void
    {
        $this->guard('reports.view');
        $this->filters += ['from' => now()->startOfMonth()->format('Y-m-d'), 'to' => today()->format('Y-m-d')];
    }

    public function updatedReport(): void
    {
        // Keep dates, drop filters the new report does not use.
        $this->filters = array_intersect_key($this->filters, array_flip(ReportBuilder::FILTERS[$this->report] ?? []));
    }

    public function export()
    {
        $this->guard('reports.view');
        $data = app(ReportBuilder::class)->build($this->report, $this->filters);
        $name = $this->report.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads it correctly
            fputcsv($out, $data['columns']);

            foreach ($data['rows'] as $row) {
                // Guard against spreadsheet formula injection from user-entered text.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $row));
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    public function render()
    {
        abort_unless(isset(ReportBuilder::REPORTS[$this->report]), 404);
        $used = ReportBuilder::FILTERS[$this->report];
        $data = app(ReportBuilder::class)->build($this->report, $this->filters);

        return $this->page('livewire.pages.reports', [
            'reports' => ReportBuilder::REPORTS,
            'used' => $used,
            'data' => $data,
            'counties' => in_array('county', $used) ? County::orderBy('name')->pluck('name', 'id') : [],
            'centres' => in_array('centre', $used) ? CollectionCentre::orderBy('name')->pluck('name', 'id') : [],
            'products' => in_array('product', $used) ? Product::orderBy('name')->pluck('name', 'id') : [],
            'customers' => in_array('customer', $used) ? Customer::orderBy('name')->pluck('name', 'id') : [],
            'sources' => in_array('supplier', $used)
                ? Supplier::orderBy('business_name')->get()->mapWithKeys(fn ($s) => ['supplier:'.$s->id => $s->business_name.' (supplier)'])
                    ->union(Farmer::orderBy('name')->get()->mapWithKeys(fn ($f) => ['farmer:'.$f->id => $f->name.' (farmer)']))
                : collect(),
        ], 'Reports');
    }
}
