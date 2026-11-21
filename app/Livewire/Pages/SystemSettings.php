<?php

namespace App\Livewire\Pages;

use App\Livewire\Concerns\Page;
use App\Models\Setting;
use App\Services\AuditLogger;
use Livewire\Component;

class SystemSettings extends Component
{
    use Page;

    public array $form = [];

    public function mount(): void
    {
        $this->guard('settings.manage');

        foreach (array_keys(Setting::DEFAULTS) as $key) {
            $this->form[$key] = (string) Setting::get($key);
        }
    }

    public function save(): void
    {
        $this->guard('settings.manage');
        $this->validate([
            'form.company_name' => ['required', 'string', 'max:120'],
            'form.company_tagline' => ['nullable', 'string', 'max:200'],
            'form.company_phone' => ['nullable', 'string', 'max:40'],
            'form.company_email' => ['nullable', 'email', 'max:120'],
            'form.company_address' => ['nullable', 'string', 'max:255'],
            'form.default_delivery_fee' => ['required', 'numeric', 'min:0'],
            'form.forecast_weeks' => ['required', 'integer', 'min:2', 'max:12'],
            'form.safety_stock_days' => ['required', 'integer', 'min:0', 'max:30'],
        ]);

        foreach ($this->form as $key => $value) {
            if (array_key_exists($key, Setting::DEFAULTS)) {
                Setting::put($key, $value);
            }
        }

        AuditLogger::log('settings_changed', null, 'System settings updated');
        $this->toast('Settings saved.');
    }

    public function render()
    {
        return $this->page('livewire.pages.system-settings', [], 'Settings');
    }
}
