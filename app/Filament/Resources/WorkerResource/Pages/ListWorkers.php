<?php

namespace App\Filament\Resources\WorkerResource\Pages;

use App\Filament\Resources\WorkerResource;
use Filament\Resources\Pages\ListRecords;

class ListWorkers extends ListRecords
{
    protected static string $resource = WorkerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
