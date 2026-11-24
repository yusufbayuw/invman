<?php

namespace App\Filament\Pages;

use App\Services\ModuleManager;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManajemenModul extends Page implements \Filament\Forms\Contracts\HasForms
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 9;
    protected static ?string $title = 'Manajemen Modul';
    protected static string $view = 'filament.pages.manajemen-modul';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return app(ModuleManager::class)->canManage(auth()->user());
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(app(ModuleManager::class)->configuration());
    }

    public function hydrate(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function form(Form $form): Form
    {
        $manager = app(ModuleManager::class);
        $sections = [];
        foreach ($manager->definitions() as $key => $definition) {
            if (isset($definition['parent'])) {
                continue;
            }

            $toggles = [$this->moduleToggle($key, $definition)];
            foreach ($manager->definitions() as $child => $childDefinition) {
                if (($childDefinition['parent'] ?? null) === $key) {
                    $toggles[] = $this->moduleToggle($child, $childDefinition);
                }
            }

            $sections[] = Section::make($definition['label'])->schema($toggles);
        }

        return $form->schema($sections)->columns(2)->statePath('data');
    }

    private function moduleToggle(string $key, array $definition): Toggle
    {
        return Toggle::make($key)->label($definition['label'])->helperText(function () use ($key, $definition): string {
            $manager = app(ModuleManager::class);
            $state = match ($manager->state($key)) {
                'active' => 'Aktif',
                'draining' => 'Dalam penonaktifan ('.$manager->pending($key).' kewajiban)',
                default => 'Nonaktif',
            };
            $dependencies = $definition['requires'] ?? [];
            if (isset($definition['parent'])) {
                $dependencies[] = $definition['parent'];
            }
            $labels = array_map(fn ($module) => $manager->definitions()[$module]['label'], $dependencies);

            return trim(($definition['description'] ?? '').' Status tersimpan: '.$state.'.'
                .($labels ? ' Bergantung pada: '.implode(', ', $labels).'. Pilihan disimpan meski induk nonaktif.' : ''));
        });
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $manager = app(ModuleManager::class);
        $state = $this->form->getState();
        $settings = [];
        foreach ($manager->definitions() as $key => $definition) {
            if (array_key_exists($key, $state)) {
                $settings[$key] = (bool) $state[$key];
            }
        }
        $manager->save(auth()->user(), $settings);
        Notification::make()->title('Pengaturan modul disimpan')->success()->send();
        $this->redirect(static::getUrl());
    }
}
