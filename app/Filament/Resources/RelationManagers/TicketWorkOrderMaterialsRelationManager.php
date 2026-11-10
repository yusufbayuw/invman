<?php

namespace App\Filament\Resources\RelationManagers;

use App\Filament\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\WorkOrderMaterialLine;
use App\Services\WorkOrderMaterialsService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TicketWorkOrderMaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrderMaterials';
    protected static ?string $title = 'Kebutuhan Material';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Ticket && $ownerRecord->maintenance !== null
            && TicketResource::canView($ownerRecord);
    }

    public function mount(): void
    {
        abort_unless(static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass()), 403);
        parent::mount();
    }

    private function mayManage(bool $manager = false): bool
    {
        return app(WorkOrderMaterialsService::class)->mayManage($this->getOwnerRecord(), auth()->user(), $manager);
    }

    public function table(Table $table): Table
    {
        return $table->description(
            'Jumlah stok dikonfirmasi manual oleh petugas, tidak mengubah stok inventaris secara otomatis. '.
            'Pembelian, QC dan penyerahan material dicatat terpisah.'
        )->columns([
            Tables\Columns\TextColumn::make('name')->label('Material')->searchable()
                ->description(fn (WorkOrderMaterialLine $record) => 'Satuan: '.$record->unit),
            Tables\Columns\TextColumn::make('required_quantity')->label('Butuh')->numeric(decimalPlaces: 3),
            Tables\Columns\TextColumn::make('stock_allocated_quantity')->label('Stok dialokasikan')->numeric(decimalPlaces: 3),
            Tables\Columns\TextColumn::make('ready')->label('Lolos & tersedia')
                ->state(fn (WorkOrderMaterialLine $record) => app(WorkOrderMaterialsService::class)->summary($record)['ready']),
            Tables\Columns\TextColumn::make('shortage')->label('Kurang')
                ->state(fn (WorkOrderMaterialLine $record) => app(WorkOrderMaterialsService::class)->summary($record)['shortage'])
                ->badge()->color(fn ($state) => (float) $state > 0 ? 'warning' : 'success'),
            Tables\Columns\TextColumn::make('issued_quantity')->label('Diserahkan')->numeric(decimalPlaces: 3),
        ])->headerActions([
            Tables\Actions\Action::make('add_requirement')->label('Tambah Kebutuhan')
                ->icon('heroicon-o-plus')->visible(fn () => $this->mayManage())
                ->form([
                    Forms\Components\TextInput::make('name')->label('Nama material')->required()->maxLength(200),
                    Forms\Components\TextInput::make('quantity')->label('Jumlah diperlukan')->required()->numeric()->gt(0),
                    Forms\Components\TextInput::make('unit')->label('Satuan')->default('pcs')->required()->maxLength(25),
                ])
                ->action(function (array $data): void {
                    app(WorkOrderMaterialsService::class)->addRequirement(
                        $this->getOwnerRecord(), auth()->user(), $data['name'], $data['unit'], $data['quantity'],
                    );
                    Notification::make()->title('Kebutuhan material dicatat')->success()->send();
                }),
        ])->actions([
            Tables\Actions\Action::make('allocate_stock')->label('Konfirmasi Stok')
                ->icon('heroicon-o-cube')->visible(fn (): bool => $this->mayManage(true))
                ->form([
                    Forms\Components\TextInput::make('quantity')->label('Jumlah stok fisik yang dialokasikan')
                        ->helperText('Harus dicek secara fisik. Ini bukan penyesuaian inventaris global.')
                        ->required()->numeric()->gt(0),
                ])
                ->action(fn (WorkOrderMaterialLine $record, array $data) =>
                    app(WorkOrderMaterialsService::class)->allocateStock($record, auth()->user(), $data['quantity'])),
            Tables\Actions\Action::make('request_purchase')->label('Ajukan Pembelian')
                ->icon('heroicon-o-shopping-cart')->color('warning')->visible(fn (): bool => $this->mayManage())
                ->form([
                    Forms\Components\TextInput::make('quantity')->label('Jumlah dibeli')->required()->numeric()->gt(0),
                    Forms\Components\Textarea::make('reason')->label('Alasan kebutuhan')->required()->minLength(5),
                ])
                ->action(fn (WorkOrderMaterialLine $record, array $data) =>
                    app(WorkOrderMaterialsService::class)->requestPurchase($record, auth()->user(), $data['quantity'], $data['reason'])),
            Tables\Actions\Action::make('issue')->label('Serahkan ke Petugas')
                ->icon('heroicon-o-arrow-right-circle')->color('success')
                ->visible(fn (WorkOrderMaterialLine $record): bool => $this->mayManage(true)
                    && (float) app(WorkOrderMaterialsService::class)->summary($record)['ready']
                        > (float) $record->issued_quantity)
                ->form([
                    Forms\Components\TextInput::make('quantity')->label('Jumlah diserahkan')->required()->numeric()->gt(0),
                ])
                ->action(fn (WorkOrderMaterialLine $record, array $data) =>
                    app(WorkOrderMaterialsService::class)->issue($record, auth()->user(), $data['quantity'])),
        ])->bulkActions([]);
    }
}
