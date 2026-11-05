<?php

namespace App\Filament\Resources\G002M007ItemResource\Pages;

use App\Filament\Resources\G002M007ItemResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateG002M007Item extends CreateRecord
{
    protected static string $resource = G002M007ItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return app(\App\Services\AssetManagerMutationGuard::class)->item($data, auth()->user());
    }

}
