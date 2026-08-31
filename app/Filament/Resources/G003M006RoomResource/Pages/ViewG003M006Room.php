<?php

namespace App\Filament\Resources\G003M006RoomResource\Pages;

use App\Filament\Resources\G003M006RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewG003M006Room extends ViewRecord
{
    protected static string $resource = G003M006RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadQrPdf')
                ->label('Unduh QR A4')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (): string => $this->getRecord()->qrCodePdfUrl()),
            Actions\EditAction::make(),
        ];
    }
}
