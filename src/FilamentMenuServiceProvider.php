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
            ->hasMigrations(
                'create_menu_items_table',
                'drop_seo_columns_from_menu_items_table',
                'make_menu_item_link_translatable',
            );
    }

    public function packageBooted(): void
    {
        $this->publishes([
            __DIR__.'/../resources/js/Sortable.min.js' => public_path('vendor/filament-menu/Sortable.min.js'),
        ], 'filament-menu-assets');
    }
}
