<?php

namespace BrainzStudios\FilamentMenu;

use BrainzStudios\FilamentMenu\Pages\ManageMenu;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentMenuPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'brainz-filament-menu';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            ManageMenu::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
