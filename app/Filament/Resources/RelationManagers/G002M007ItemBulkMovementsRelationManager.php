<?php

namespace App\Filament\Resources\RelationManagers;

use App\Services\AssetBulkStockService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

class G002M007ItemBulkMovementsRelationManager extends ReadOnlyRelationManager
{
    protected static string $relationship = 'bulkMovements';

    protected static ?string $title = 'Pergerakan Stok Bulk';

    public function table(Table $table): Table
    {
        return parent::table($table)->headerActions([
            Action::make('adjustStock')->label('Catat Perubahan Stok')
                ->authorize(fn () => ! $this->isReadOnly() && $this->getOwnerRecord()->isBulk() && auth()->user()?->can('update', $this->getOwnerRecord()))
                ->form([
                    TextInput::make('delta')->label('Perubahan jumlah (+ / −)')->integer()->required(),
                    Textarea::make('reason')->label('Alasan')->required()->minLength(5)->maxLength(120),
                ])->action(fn (array $data) => app(AssetBulkStockService::class)->adjust(
                    $this->getOwnerRecord(), (int) $data['delta'], $data['reason'], auth()->user(),
                )),
        ]);
    }
}
