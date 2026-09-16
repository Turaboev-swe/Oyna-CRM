<?php

namespace App\Filament\Resources\PriceSettingResource\Pages;

use App\Filament\Resources\PriceSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditPriceSetting extends EditRecord
{
    protected static string $resource = PriceSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }
}
