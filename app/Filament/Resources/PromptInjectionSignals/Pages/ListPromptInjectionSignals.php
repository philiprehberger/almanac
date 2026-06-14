<?php

namespace App\Filament\Resources\PromptInjectionSignals\Pages;

use App\Filament\Resources\PromptInjectionSignals\PromptInjectionSignalResource;
use Filament\Resources\Pages\ListRecords;

class ListPromptInjectionSignals extends ListRecords
{
    protected static string $resource = PromptInjectionSignalResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
