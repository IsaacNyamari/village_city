<div class="space-y-6">
    <x-ui.header title="Settings" description="Company details used on invoices, and defaults for orders and the intelligence module." />
    <form wire:submit="save" class="space-y-6">
        <x-ui.panel title="Company" padded>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.company_name" label="Company name *" />
                <flux:input wire:model="form.company_tagline" label="Tagline" />
                <flux:input wire:model="form.company_phone" label="Phone" />
                <flux:input type="email" wire:model="form.company_email" label="Email" />
                <div class="sm:col-span-2"><flux:input wire:model="form.company_address" label="Address" /></div>
            </div>
        </x-ui.panel>
        <x-ui.panel title="Commerce & intelligence" padded>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input type="number" step="0.01" min="0" wire:model="form.default_delivery_fee" label="Default delivery charge (KES) *" description="Applied to marketplace and new orders." />
                <flux:input type="number" min="2" max="12" wire:model="form.forecast_weeks" label="Forecast history window (weeks) *" />
                <flux:input type="number" min="0" max="30" wire:model="form.safety_stock_days" label="Safety stock (days of demand) *" />
            </div>
        </x-ui.panel>
        <div><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Save settings</flux:button></div>
    </form>
</div>
