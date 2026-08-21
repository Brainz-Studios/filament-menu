# brainz-studios/filament-menu

Filament menu tree admin, path building, global slug uniqueness across linkable content, and GraphQL helpers.

## Requirements

- PHP 8.2+
- Laravel 11 / 12 / 13
- Filament 5
- Spatie Translatable
- Lighthouse (for GraphQL)

## Publish to GitHub + Satis

1. Push this folder as its own repository.
2. Tag a version and mirror into `https://satis.brainzstudios.cz`.
3. Require `brainz-studios/filament-menu` from Satis in the host app.

## Install

```bash
composer require brainz-studios/filament-menu
php artisan vendor:publish --tag=filament-menu-migrations
php artisan vendor:publish --tag=filament-menu-assets
php artisan migrate
```

Optional config:

```bash
php artisan vendor:publish --tag=filament-menu-config
```

Register the plugin:

```php
use BrainzStudios\FilamentMenu\FilamentMenuPlugin;

$panel->plugin(FilamentMenuPlugin::make());
```

## Linkable registry

```php
// config/filament-menu.php
'locales' => ['cs', 'en'],
'linkables' => [
    'page' => \App\Models\Page::class,
    'event' => \App\Models\Event::class,
    // ...
],
```

Expected on linkable models:

- Spatie `HasTranslations` with `slug` (and preferably `title` / `name` / `label` for option labels)
- Optional `scopePublished()` for GraphQL `linkable` resolution

## GraphQL

Import / merge [`resources/graphql/schema.graphql`](resources/graphql/schema.graphql). Host must define:

- `LocalizedString`, `Breadcrumbs` / `BreadcrumbItem` (keys matching your locales)
- `union MenuLinkable` with your content types + package resolveType

Wire `menu_url` / `breadcrumbs` on content types to:

- `BrainzStudios\FilamentMenu\GraphQL\Resolvers\ResolveEntityMenuUrl`
- `BrainzStudios\FilamentMenu\GraphQL\Resolvers\ResolveEntityBreadcrumbs`

Services available in the host:

- `MenuPathBuilder::entityForPath()` / `nodeForPath()`
- `GlobalSlugGuard` for content form slug validation

## Host notes

- No Filament Shield / Knowledge Base dependency.
- Publish Sortable asset tag once so the admin tree drag-and-drop works.
