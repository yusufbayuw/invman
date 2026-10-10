<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Services\TicketService;
use App\Services\TicketVisibility;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public function getRecord(): \Illuminate\Database\Eloquent\Model
    {
        $record = parent::getRecord();

        abort_unless(app(TicketVisibility::class)->canView(auth()->user(), $record), 404);

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('comment')
                ->label('Tambah Komentar')->icon('heroicon-o-chat-bubble-left-right')
                ->form([
                    Forms\Components\Textarea::make('body')->label('Komentar')->required()->minLength(2)->maxLength(10000),
                    Forms\Components\FileUpload::make('files')->label('Foto/PDF (opsional)')
                        ->multiple()->maxFiles(3)->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg','image/png','application/pdf'])
                        ->disk('local')->directory('tickets/attachments')->visibility('private'),
                    Forms\Components\Toggle::make('internal')->label('Catatan khusus pengelola')
                        ->visible(fn (): bool => app(TicketVisibility::class)->canManage(auth()->user(), $this->getRecord())),
                ])
                ->action(function (array $data): void {
                    $comment = app(TicketService::class)->comment(
                        $this->getRecord(), auth()->user(), $data['body'], (bool) ($data['internal'] ?? false),
                    );
                    if (filled($data['files'] ?? [])) {
                        app(TicketService::class)->attach($this->getRecord(), auth()->user(), array_values($data['files']), $comment);
                    }
                    Notification::make()->title('Komentar ditambahkan')->success()->send();
                }),
            Actions\Action::make('assign')
                ->label('Tugaskan')->icon('heroicon-o-user-plus')
                ->visible(fn (): bool => app(TicketVisibility::class)->canManage(auth()->user(), $this->getRecord()))
                ->form([
                    Forms\Components\Select::make('user_id')->label('Petugas')
                        ->options(fn (): array => $this->assigneeOptions())->searchable()->nullable(),
                    Forms\Components\Select::make('priority')->label('Prioritas')
                        ->options(['low'=>'Rendah','normal'=>'Normal','high'=>'Tinggi','critical'=>'Kritis'])
                        ->visible(fn (): bool => auth()->user()->isFacility()),
                ])
                ->action(function (array $data): void {
                    app(TicketService::class)->assign(
                        $this->getRecord(), auth()->user(), $data['user_id'] ?? null, $data['priority'] ?? null,
                    );
                    Notification::make()->title('Penugasan diperbarui')->success()->send();
                }),
            Actions\Action::make('status')
                ->label('Proses Status')->icon('heroicon-o-arrow-path')
                ->form([
                    Forms\Components\Select::make('to')->label('Status baru')->required()
                        ->options(fn (): array => $this->availableTransitions()),
                    Forms\Components\Textarea::make('notes')->label('Catatan perubahan')->maxLength(2000),
                ])
                ->action(function (array $data): void {
                    app(TicketService::class)->transition($this->getRecord(), auth()->user(), $data['to'], $data['notes'] ?? null);
                    Notification::make()->title('Status diperbarui')->success()->send();
                }),
        ];
    }

    private function assigneeOptions(): array
    {
        $record = $this->getRecord();
        $ids = $record->management?->users()->pluck('users.id') ?? collect();
        return \App\Models\User::query()->whereKey($ids)->orderBy('name')->pluck('name','id')->all();
    }

    private function availableTransitions(): array
    {
        return app(TicketService::class)->availableTransitions($this->getRecord(), auth()->user());
    }
}
