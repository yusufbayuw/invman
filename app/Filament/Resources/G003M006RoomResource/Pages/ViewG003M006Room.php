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
                ->label('Lihat QR A4')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading('Pratinjau QR Ruangan A4')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup')
                ->modalContent(fn () => view('filament.components.pdf-preview-modal', [
                    'title' => 'QR Ruangan '.$this->getRecord()->name,
                    'previewUrl' => route('public.rooms.qrcode-pdf', [
                        'room' => $this->getRecord()->qr_uuid, 'preview' => 1,
                    ]),
                    'downloadUrl' => $this->getRecord()->qrCodePdfUrl(),
                ])),
            Actions\EditAction::make(),
        ];
    }
}
