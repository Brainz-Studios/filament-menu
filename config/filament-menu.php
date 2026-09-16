<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    */
    'locales' => ['cs', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Locale tab labels (Filament mutation tabs)
    |--------------------------------------------------------------------------
    */
    'locale_labels' => [
        'cs' => 'čeština',
        'en' => 'english',
    ],

    /*
    |--------------------------------------------------------------------------
    | Linkable content types
    |--------------------------------------------------------------------------
    |
    | Map of type key => Eloquent model class. Models should expose a
    | translatable `slug` attribute (Spatie Translatable) and ideally a
    | `scopePublished` / `is_published` for GraphQL resolution.
    |
    | Example:
    | 'page' => \App\Models\Page::class,
    |
    */
    'linkables' => [],

    /*
    |--------------------------------------------------------------------------
    | Optional label resolver for Filament internal-link options
    |--------------------------------------------------------------------------
    |
    | null = built-in title/name/label heuristic (incl. first_name/last_name)
    | Or a callable class-string implementing __invoke(Model $model, string $locale): string
    |
    */
    'option_label_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Breadcrumb home label translation key
    |--------------------------------------------------------------------------
    */
    'breadcrumb_home_key' => 'filament-menu::menu.breadcrumbs.home',

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation' => [
        'icon' => 'heroicon-o-bars-3',
        'sort' => 10,
        'group' => null,
    ],

];
