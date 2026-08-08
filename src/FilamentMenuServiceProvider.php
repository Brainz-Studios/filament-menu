<?php

namespace BrainzStudios\FilamentMenu;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentMenuServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-menu')
            ->hasConfigFile('filament-menu')
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('create_menu_items_table');
    }

    public function packageBooted(): void
    {
        $this->publishes([
            __DIR__.'/../resources/js/Sortable.min.js' => public_path('vendor/filament-menu/Sortable.min.js'),
        ], 'filament-menu-assets');
    }
}
