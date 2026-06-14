<?php

namespace App\Filament\Resources\PromptInjectionSignals;

use App\Filament\Resources\PromptInjectionSignals\Pages\ListPromptInjectionSignals;
use App\Models\PromptInjectionSignal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromptInjectionSignalResource extends Resource
{
    protected static ?string $model = PromptInjectionSignal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Prompt-injection signals';

    protected static ?string $modelLabel = 'Prompt-injection signal';

    protected static ?string $pluralModelLabel = 'Prompt-injection signals';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i:s a')
                    ->sortable(),
                TextColumn::make('signal_kind')
                    ->label('Trip')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        PromptInjectionSignal::SIGNAL_URL_OUTSIDE_SOURCES => 'danger',
                        PromptInjectionSignal::SIGNAL_IMAGE_TAG => 'danger',
                        PromptInjectionSignal::SIGNAL_HALLUCINATED_CITATION => 'warning',
                        PromptInjectionSignal::SIGNAL_SCHEMA_VIOLATION => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => str_replace('_', ' ', $state)),
                TextColumn::make('query_id')
                    ->label('Query')
                    ->fontFamily('mono')
                    ->limit(12)
                    ->tooltip(fn (PromptInjectionSignal $r) => $r->query_id),
                TextColumn::make('details')
                    ->label('Details')
                    ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_SLASHES) : '—')
                    ->limit(80)
                    ->fontFamily('mono')
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromptInjectionSignals::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
