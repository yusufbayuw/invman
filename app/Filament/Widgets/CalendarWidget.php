<?php

namespace App\Filament\Widgets;

use App\Models\G004M008Activity;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions\ViewAction;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    public Model | string | null $model = G004M008Activity::class;

    protected function headerActions(): array
    {
        return [
            //
        ];
    }


    protected function modalActions(): array
    {
        return [
            //
        ];
    }

    // change record atribute in modal
    public function getRecordTitle(): string
    {
        return $this->record->name ?? 'Kegiatan';
    }

    protected function viewAction(): ViewAction
    {
        return ViewAction::make('view')
            ->label('Lihat Kegiatan')
            ->icon('heroicon-o-eye')
            ->color('primary')
            ->modalHeading(fn() => 'Kegiatan: ' . $this->record->name)
            ->modalWidth('2xl')
            ->infolist([
                \Filament\Infolists\Components\Split::make([
                    \Filament\Infolists\Components\Section::make([
                        \Filament\Infolists\Components\TextEntry::make('name')
                            ->label('Nama Kegiatan')
                            ->weight('bold')
                            ->size('md'),
                        \Filament\Infolists\Components\TextEntry::make('start_time')
                            ->label('Mulai')
                            ->dateTime()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('end_time')
                            ->label('Selesai')
                            ->dateTime()
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('user.name')
                            ->label('Diajukan Oleh')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('unit.name')
                            ->label('Unit')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('description')
                            ->label('Deskripsi')
                            ->size('md')
                            ->inlineLabel(),
                        \Filament\Infolists\Components\TextEntry::make('attachment')
                            ->label('Lampiran')
                            ->inlineLabel(),
                    ]),
                    \Filament\Infolists\Components\Section::make([
                        // show list of all item reservations related to this activity
                        \Filament\Infolists\Components\RepeatableEntry::make('room_reservation')
                            ->label('Reservasi Ruangan')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('room.name')
                                    ->label('Nama')
                                    ->inlineLabel(),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->inlineLabel(),
                            ]),
                            \Filament\Infolists\Components\RepeatableEntry::make('item_reservation')
                            ->label('Reservasi Barang')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('item.name')
                                    ->label('Nama')
                                    ->inlineLabel(),
                                \Filament\Infolists\Components\TextEntry::make('quantity')
                                    ->label('Jumlah')
                                    ->inlineLabel(),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->inlineLabel(),
                            ]),
                    ]),
                ])->from('md')->columnSpanFull(),
            ])
            ->action(fn() => $this->record);
    }

    public function fetchEvents(array $fetchInfo): array
    {
        return G004M008Activity::where('start_time', '>=', $fetchInfo['start'])
            ->where('end_time', '<=', $fetchInfo['end'])
            ->get()
            ->map(function (G004M008Activity $task) {
                return [
                    'id'    => $task->id,
                    'title' => $task->name,
                    'start' => $task->start_time,
                    'end'   => $task->end_time,
                ];
            })
            ->toArray();
    }

    public static function canView(): bool
    {
        return true;
    }
}
