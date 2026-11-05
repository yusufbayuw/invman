<?php

namespace App\Filament\Resources\G002M015ItemInstanceResource\RelationManagers;

use App\Models\AssetRelation;
use App\Models\G002M015ItemInstance;
use App\Services\AssetRelationService;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetRelationsRelationManager extends RelationManager
{
    protected static string $relationship = 'childRelations';
    protected static ?string $title = 'Hubungan dan Komponen Aset';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('childInstance.code')->label('Kode Aset')->searchable(),
                Tables\Columns\TextColumn::make('childInstance.name')->label('Aset Terkait'),
                Tables\Columns\TextColumn::make('relation_type')->label('Hubungan')->badge(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('connect')
                    ->label('Hubungkan Aset')
                    ->visible(fn () => auth()->user()?->can('update', $this->getOwnerRecord()) ?? false)
                    ->form([
                        Forms\Components\Select::make('relation_type')
                            ->label('Jenis Hubungan')
                            ->options([
                                'component' => 'Komponen / Bagian dari',
                                'contained' => 'Berada di dalam aset',
                                'installed' => 'Terpasang pada aset',
                                'bundle' => 'Anggota paket',
                            ])->required(),
                        Forms\Components\Select::make('child_instance_id')
                            ->label('Pilih Aset Satuan')
                            ->options(function (): array {
                                /** @var G002M015ItemInstance $parent */
                                $parent = $this->getOwnerRecord();
                                return G002M015ItemInstance::query()
                                    ->whereKeyNot($parent->id)
                                    ->where('g001_m001_unit_id', $parent->g001_m001_unit_id)
                                    ->whereHas('item', fn (Builder $q) => $q->where(
                                        'g002_m003_item_management_id',
                                        $parent->item?->g002_m003_item_management_id,
                                    ))
                                    ->orderBy('name')->limit(200)
                                    ->get()->mapWithKeys(fn ($u) => [$u->id => ($u->code ?: $u->id).' - '.$u->name])
                                    ->all();
                            })->searchable()->required(),
                    ])
                    ->action(function (array $data): void {
                        app(AssetRelationService::class)->connect(
                            (int) $this->getOwnerRecord()->id,
                            (int) $data['child_instance_id'],
                            $data['relation_type'], auth()->user(),
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('unlink')
                    ->label('Lepaskan Relasi')->color('danger')->requiresConfirmation()
                    ->visible(fn (AssetRelation $record) => auth()->user()?->can('update', $record->parentInstance) ?? false)
                    ->action(fn (AssetRelation $record) => app(AssetRelationService::class)->disconnect($record, auth()->user())),
            ]);
    }
}
