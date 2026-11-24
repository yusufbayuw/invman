<?php

namespace App\Filament\Support;

use App\Services\ModuleManager;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

/** A module restriction must be evaluated before the existing Shield permission. */
abstract class ModuleResource extends Resource
{
    protected static string $moduleKey;

    // History resources stay readable; draining setup may be needed for current work.
    protected static bool $moduleHistory = false;
    protected static bool $moduleDrainSetup = false;

    public static function moduleEnabled(): bool
    {
        return app(ModuleManager::class)->enabled(static::$moduleKey);
    }

    public static function moduleVisible(): bool
    {
        return static::moduleEnabled()
            || ((static::$moduleHistory || static::$moduleDrainSetup)
                && app(ModuleManager::class)->state(static::$moduleKey) === 'draining');
    }

    protected static function moduleReadable(): bool
    {
        return static::$moduleHistory || static::moduleVisible();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::moduleVisible() && parent::shouldRegisterNavigation();
    }

    public static function canGloballySearch(): bool
    {
        return static::moduleVisible() && parent::canGloballySearch();
    }

    public static function canViewAny(): bool
    {
        return static::moduleReadable() && parent::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::moduleReadable() && parent::canView($record);
    }

    public static function canCreate(): bool
    {
        return static::moduleEnabled() && parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        return static::moduleReadable() && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::moduleEnabled() && parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::moduleEnabled() && parent::canDeleteAny();
    }

    public static function canReplicate(Model $record): bool
    {
        return static::moduleEnabled() && parent::canReplicate($record);
    }
}
