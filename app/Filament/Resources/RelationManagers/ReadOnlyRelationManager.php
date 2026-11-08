<?php

namespace App\Filament\Resources\RelationManagers;

use App\Models\Ticket;
use App\Services\TicketVisibility;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Immutable workflow records expose complete safe metadata, never raw storage paths or mutation actions. */
abstract class ReadOnlyRelationManager extends RelationManager
{
    protected static bool $internal = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $resource = $pageClass::getResource();
        if (! auth()->user()?->is_enabled || ! ($resource::canView($ownerRecord) || $resource::canEdit($ownerRecord))) {
            return false;
        }
        if (static::$internal && $ownerRecord instanceof Ticket) {
            return app(TicketVisibility::class)->canManage(auth()->user(), $ownerRecord);
        }

        return ! static::$internal || auth()->user()->isFacility();
    }

    public function mount(): void
    {
        abort_unless(static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass()), 403);
        parent::mount();
    }

    public function hydrate(): void
    {
        abort_unless(static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass()), 403);
    }

    protected function fields(): array
    {
        $model = $this->getRelationship()->getRelated();

        return array_values(array_unique(array_diff([$model->getKeyName(), ...$model->getFillable(), 'created_at', ...($model->usesTimestamps() ? ['updated_at'] : [])], [
            'disk', 'path', 'proof_path', 'photo', 'signature_path', 'candidate_user_ids', 'files', 'evidence_path',
        ])));
    }

    protected static function fieldLabel(string $field): string
    {
        return [
            'id' => 'ID', 'created_at' => 'Dicatat pada', 'updated_at' => 'Diperbarui pada',
            'notes' => 'Catatan', 'reason' => 'Alasan', 'body' => 'Isi komentar', 'is_internal' => 'Komentar internal',
            'action' => 'Tindakan', 'actor_id' => 'ID pelaku', 'user_id' => 'ID pengguna', 'subject_id' => 'ID subjek',
            'from_status' => 'Status sebelumnya', 'to_status' => 'Status berikutnya', 'changed_by' => 'Diubah oleh (ID)',
            'from_value' => 'Nilai sebelumnya', 'to_value' => 'Nilai berikutnya', 'is_ok' => 'Kondisi baik', 'stage' => 'Tahap',
            'rating' => 'Penilaian', 'review' => 'Ulasan', 'delta' => 'Perubahan jumlah', 'performed_by' => 'Dicatat oleh (ID)',
            'status' => 'Status', 'label' => 'Tahap persetujuan', 'sequence' => 'Urutan', 'decided_at' => 'Waktu keputusan',
            'decided_by' => 'Pengambil keputusan (ID)', 'original_name' => 'Nama berkas', 'mime_type' => 'Jenis berkas',
            'size' => 'Ukuran (byte)', 'revision' => 'Revisi', 'superseded_at' => 'Digantikan pada',
            'receipt_number' => 'Nomor bukti', 'direction' => 'Arah serah terima', 'received_at' => 'Diterima pada',
            'reservation_type' => 'Jenis reservasi', 'reservation_id' => 'ID reservasi', 'g004_m008_activity_id' => 'ID pengajuan',
            'relation_type' => 'Jenis hubungan', 'parent_instance_id' => 'ID aset induk', 'child_instance_id' => 'ID aset komponen',
            'ticket_id' => 'ID laporan', 'ticket_comment_id' => 'ID komentar', 'uploaded_by' => 'Pengunggah (ID)',
        ][$field] ?? Str::headline($field);
    }

    protected static function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i');
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return (string) $value;
    }

    public function form(Form $form): Form
    {
        return $form->schema(array_map(fn ($field) => Placeholder::make($field)->label(static::fieldLabel($field))
            ->content(fn (Model $record): string => static::display($record->getAttribute($field))), $this->fields()))->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->columns(array_map(fn ($field, $index) => Tables\Columns\TextColumn::make($field)
            ->label(static::fieldLabel($field))->formatStateUsing(fn ($state) => static::display($state))
            ->wrap()->limit(100)->toggleable(isToggledHiddenByDefault: $index > 6), $this->fields(), array_keys($this->fields())))
            ->defaultSort('created_at', 'desc')->description('Catatan ini dibuat oleh proses aplikasi. Gunakan tindakan proses untuk mencatat perubahan; riwayat tidak ditimpa atau dihapus.')
            ->headerActions([])->actions([Tables\Actions\ViewAction::make()->label('Lihat rincian')])->bulkActions([]);
    }

    protected function canView(Model $record): bool
    {
        return static::canViewForRecord($this->getOwnerRecord(), $this->getPageClass());
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }

    protected function canDeleteAny(): bool
    {
        return false;
    }
}
