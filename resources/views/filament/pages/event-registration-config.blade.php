<x-filament-panels::page>
    <form wire:submit="save" class="fi-sc-form">
        {{ $this->form }}

        <x-filament::actions alignment="end">
            <x-filament::button type="submit">
                {{ __('event-registrations::messages.config.save') }}
            </x-filament::button>
        </x-filament::actions>
    </form>
</x-filament-panels::page>
