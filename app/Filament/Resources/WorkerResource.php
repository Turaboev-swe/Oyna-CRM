<?php

namespace App\Filament\Resources;

use App\Enums\WorkerStatus;
use App\Filament\Resources\WorkerResource\Pages;
use App\Models\Worker;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WorkerResource extends Resource
{
    protected static ?string $model = Worker::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Ishchilar';

    protected static ?string $modelLabel = 'ishchi';

    protected static ?string $pluralModelLabel = 'ishchilar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('telegram_id')
                    ->label('Telegram ID')
                    ->disabled(),
                Forms\Components\TextInput::make('ism')
                    ->label('Ismi')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('telefon')
                    ->label('Telefon')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(collect(WorkerStatus::cases())->mapWithKeys(fn (WorkerStatus $status) => [$status->value => $status->label()]))
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ism')
                    ->label('Ismi')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telefon')
                    ->label('Telefon'),
                Tables\Columns\TextColumn::make('telegram_id')
                    ->label('Telegram ID')
                    ->searchable(),
                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options(collect(WorkerStatus::cases())->mapWithKeys(fn (WorkerStatus $status) => [$status->value => $status->label()])),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(WorkerStatus::cases())->mapWithKeys(fn (WorkerStatus $status) => [$status->value => $status->label()])),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkers::route('/'),
            'edit' => Pages\EditWorker::route('/{record}/edit'),
        ];
    }
}
