<?php

namespace App\Filament\Widgets;

use App\Enums\ReservationStatus;
use App\Filament\Resources\G004M008ActivityResource;
use App\Filament\Widgets\Concerns\InteractsWithLoanDashboardFilters;
use App\Models\G004M008Activity;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentLoanRequests extends BaseWidget
{
    use InteractsWithLoanDashboardFilters;
    use InteractsWithPageFilters;

    protected static ?int $sort = -70;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pengajuan Terbaru')
            ->description('Daftar operasional terbaru sesuai filter dasbor.')
            ->query($this->loanQuery()->with(['user', 'unit'])->latest('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Kegiatan')
                    ->searchable()
                    ->weight('medium')
                    ->wrap(),
                Tables\Columns\TextColumn::make('unit.name')
                    ->label('Unit')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Pemohon')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->label('Jadwal')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ReservationStatus::tryFrom($state)?->label() ?? $state ?? '-')
                    ->color(fn (?string $state): string => ReservationStatus::tryFrom($state)?->color() ?? 'gray'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (G004M008Activity $record): string => G004M008ActivityResource::getUrl('view', ['record' => $record])),
            ])
            ->recordUrl(fn (G004M008Activity $record): string => G004M008ActivityResource::getUrl('view', ['record' => $record]))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->poll('30s')
            ->emptyStateHeading('Belum ada pengajuan pada periode ini')
            ->emptyStateIcon('heroicon-o-inbox');
    }
}
