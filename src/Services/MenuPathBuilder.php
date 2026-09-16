<?php

namespace BrainzStudios\FilamentMenu\Services;

use BrainzStudios\FilamentMenu\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

class MenuPathBuilder
{
    public function __construct(
        private LinkableRegistry $registry,
    ) {}

    /**
     * @return array<string, class-string<Model>>
     */
    public static function linkableModels(): array
    {
        return app(LinkableRegistry::class)->models();
    }

    /**
     * @return list<string>
     */
    public static function linkableTypes(): array
    {
        return app(LinkableRegistry::class)->types();
    }

    public static function graphqlTypeName(string $type): ?string
    {
        return app(LinkableRegistry::class)->graphqlTypeName($type);
    }

    public function pathForMenuItem(MenuItem $item, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $locales = $this->registry->locales();

        if ($item->type === MenuItem::TYPE_EXTERNAL) {
            $url = $item->getTranslation('link', $locale);

            if (! filled($url)) {
                foreach ($locales as $fallback) {
                    $url = $item->getTranslation('link', $fallback);

                    if (filled($url)) {
                        break;
                    }
                }
            }

            return filled($url) ? $url : null;
        }

        $chain = $this->ancestorChain($item);
        $segments = [];

        foreach ($chain as $menuItem) {
            if ($menuItem->type === MenuItem::TYPE_EXTERNAL) {
                continue;
            }

            if ($menuItem->type === MenuItem::TYPE_NODE) {
                $slug = $this->translated($menuItem, 'slug', $locale, $locales);

                if ($slug === null || $slug === '') {
                    return null;
                }

                $segments[] = $slug;

                continue;
            }

            if ($menuItem->type !== MenuItem::TYPE_INTERNAL) {
                continue;
            }

            $slug = $this->slugForInternalLink($menuItem->link, $locale);

            if ($slug === null || $slug === '') {
                return null;
            }

            $segments[] = $slug;
        }

        if ($segments === []) {
            return null;
        }

        return '/'.implode('/', $segments);
    }

    public function pathForEntity(Model $entity, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $locales = $this->registry->locales();

        if ($entity instanceof MenuItem) {
            return $this->pathForMenuItem($entity, $locale);
        }

        $type = $this->entityType($entity);

        if ($type !== null) {
            $link = "{$type}:{$entity->getKey()}";

            $menuItem = MenuItem::query()
                ->where('type', 'internal')
                ->whereLink($link)
                ->where('is_published', true)
                ->first();

            if ($menuItem !== null) {
                return $this->pathForMenuItem($menuItem, $locale);
            }
        }

        if (! method_exists($entity, 'getTranslation')) {
            return null;
        }

        $slug = $this->translated($entity, 'slug', $locale, $locales);

        return filled($slug) ? '/'.$slug : null;
    }

    /**
     * @return array<string, list<array{label: string, menu_url: ?string}>>
     */
    public function breadcrumbsForEntity(Model $entity): array
    {
        $result = [];

        foreach ($this->registry->locales() as $locale) {
            $result[$locale] = $this->breadcrumbItemsForEntity($entity, $locale);
        }

        return $result;
    }

    public function entityForPath(string $path): ?Model
    {
        $normalized = trim($path, '/');

        if ($normalized === '') {
            return null;
        }

        $menuItems = MenuItem::query()
            ->published()
            ->where('type', MenuItem::TYPE_INTERNAL)
            ->get()
            ->filter(fn (MenuItem $item): bool => MenuItem::parseInternalLink($item->link) !== null);

        foreach ($this->registry->locales() as $locale) {
            foreach ($menuItems as $menuItem) {
                $itemPath = $this->pathForMenuItem($menuItem, $locale);

                if ($itemPath === null) {
                    continue;
                }

                if (trim($itemPath, '/') !== $normalized) {
                    continue;
                }

                $parsed = MenuItem::parseInternalLink($menuItem->link);

                if ($parsed === null) {
                    continue;
                }

                $entity = $this->resolveEntity($parsed['type'], $parsed['id']);

                if ($entity === null) {
                    return null;
                }

                if (method_exists($entity, 'scopePublished')) {
                    return $entity::query()->published()->find($entity->getKey());
                }

                return $entity;
            }
        }

        return $this->nodeForPath($normalized);
    }

    public function nodeForPath(string $path): ?MenuItem
    {
        $normalized = trim($path, '/');

        if ($normalized === '') {
            return null;
        }

        $nodes = MenuItem::query()
            ->published()
            ->where('type', MenuItem::TYPE_NODE)
            ->get();

        $locales = $this->registry->locales();

        foreach ($locales as $locale) {
            foreach ($nodes as $node) {
                $itemPath = $this->pathForMenuItem($node, $locale);

                if ($itemPath === null) {
                    continue;
                }

                if (trim($itemPath, '/') === $normalized) {
                    return $node;
                }
            }
        }

        if (! str_contains($normalized, '/')) {
            $matches = $nodes->filter(function (MenuItem $node) use ($normalized, $locales): bool {
                foreach ($locales as $locale) {
                    if ($normalized === ($node->getTranslation('slug', $locale) ?: '')) {
                        return true;
                    }
                }

                return false;
            })->values();

            if ($matches->count() === 1) {
                return $matches->first();
            }
        }

        return null;
    }

    public function resolveEntity(string $type, int $id, bool $publishedOnly = false): ?Model
    {
        $modelClass = $this->registry->modelFor($type);

        if ($modelClass === null) {
            return null;
        }

        $query = $modelClass::query();

        if ($publishedOnly && method_exists($modelClass, 'scopePublished')) {
            $query->published();
        }

        return $query->whereKey($id)->first();
    }

    public function entityType(Model $entity): ?string
    {
        return $this->registry->typeFor($entity);
    }

    /**
     * @param  list<string>|null  $excludeLinks
     * @return array<string, string>|array<string, array<string, string>>
     */
    public function internalLinkOptions(?array $excludeLinks = null, ?string $locale = null, ?string $onlyType = null): array
    {
        $locale ??= app()->getLocale();
        $excludeLinks ??= [];
        $models = $this->registry->models();

        if ($onlyType !== null) {
            $modelClass = $models[$onlyType] ?? null;

            if ($modelClass === null) {
                return [];
            }

            return $this->optionsForType($onlyType, $modelClass, $excludeLinks, $locale);
        }

        $groups = [];

        foreach ($models as $type => $modelClass) {
            $groups[$this->registry->labelFor($type)] = $this->optionsForType($type, $modelClass, $excludeLinks, $locale);
        }

        return array_filter($groups);
    }

    public function internalLinkOptionLabel(?string $link): ?string
    {
        if (! filled($link)) {
            return null;
        }

        $parsed = MenuItem::parseInternalLink($link);

        if ($parsed === null) {
            return null;
        }

        $entity = $this->resolveEntity($parsed['type'], $parsed['id']);

        if ($entity === null) {
            return null;
        }

        return $this->optionLabel($entity, app()->getLocale());
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $excludeLinks
     * @return array<string, string>
     */
    private function optionsForType(string $type, string $modelClass, array $excludeLinks, string $locale): array
    {
        return $this->optionsQuery($modelClass, $type)
            ->get()
            ->reject(fn (Model $model): bool => in_array("{$type}:{$model->getKey()}", $excludeLinks, true))
            ->mapWithKeys(function (Model $model) use ($type, $locale): array {
                return ["{$type}:{$model->getKey()}" => $this->optionLabel($model, $locale)];
            })
            ->all();
    }

    /**
     * @return list<array{label: string, menu_url: ?string}>
     */
    private function breadcrumbItemsForEntity(Model $entity, string $locale): array
    {
        $locales = $this->registry->locales();
        $items = [[
            'label' => $this->homeLabel($locale),
            'menu_url' => '/',
        ]];

        if ($entity instanceof MenuItem) {
            foreach ($this->ancestorChain($entity) as $item) {
                $label = $this->translated($item, 'label', $locale, $locales);

                if (! filled($label)) {
                    continue;
                }

                $items[] = [
                    'label' => $label,
                    'menu_url' => $this->pathForMenuItem($item, $locale),
                ];
            }

            return $items;
        }

        $type = $this->entityType($entity);

        if ($type !== null) {
            $link = "{$type}:{$entity->getKey()}";

            $menuItem = MenuItem::query()
                ->where('type', 'internal')
                ->whereLink($link)
                ->where('is_published', true)
                ->first();

            if ($menuItem !== null) {
                foreach ($this->ancestorChain($menuItem) as $item) {
                    $label = $this->translated($item, 'label', $locale, $locales);

                    if (! filled($label)) {
                        continue;
                    }

                    $items[] = [
                        'label' => $label,
                        'menu_url' => $this->pathForMenuItem($item, $locale),
                    ];
                }

                return $items;
            }
        }

        $entityLabel = $this->optionLabel($entity, $locale);

        if (filled($entityLabel)) {
            $items[] = [
                'label' => $entityLabel,
                'menu_url' => $this->pathForEntity($entity, $locale),
            ];
        }

        return $items;
    }

    private function homeLabel(string $locale): string
    {
        $key = (string) config('filament-menu.breadcrumb_home_key', 'filament-menu::menu.breadcrumbs.home');

        return Lang::get($key, locale: $locale);
    }

    /**
     * @return Collection<int, MenuItem>
     */
    private function ancestorChain(MenuItem $item): Collection
    {
        $chain = collect([$item]);
        $parentId = $item->parent_id;

        while ($parentId !== null) {
            $parent = MenuItem::query()->find($parentId);

            if ($parent === null) {
                break;
            }

            $chain->prepend($parent);
            $parentId = $parent->parent_id;
        }

        return $chain;
    }

    private function slugForInternalLink(string $link, string $locale): ?string
    {
        $parsed = MenuItem::parseInternalLink($link);

        if ($parsed === null) {
            return null;
        }

        $entity = $this->resolveEntity($parsed['type'], $parsed['id']);

        if ($entity === null || ! method_exists($entity, 'getTranslation')) {
            return null;
        }

        return $this->translated($entity, 'slug', $locale, $this->registry->locales());
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return Builder<Model>
     */
    private function optionsQuery(string $modelClass, string $type): Builder
    {
        $query = $modelClass::query();

        if (str_ends_with($type, '_category') || str_ends_with($type, '_tag') || $type === 'program') {
            if ($this->hasColumn($modelClass, 'sort_order')) {
                $query->orderBy('sort_order');
            }
        }

        return $query->orderBy('id');
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function hasColumn(string $modelClass, string $column): bool
    {
        try {
            return in_array($column, (new $modelClass)->getConnection()->getSchemaBuilder()->getColumnListing((new $modelClass)->getTable()), true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function optionLabel(Model $model, string $locale): string
    {
        $resolver = config('filament-menu.option_label_resolver');

        if (is_string($resolver) && class_exists($resolver)) {
            return (string) app($resolver)($model, $locale);
        }

        if (is_callable($resolver)) {
            return (string) $resolver($model, $locale);
        }

        $locales = $this->registry->locales();

        if (method_exists($model, 'getTranslation')) {
            foreach (['title', 'name', 'label', 'first_name'] as $attribute) {
                if ($attribute === 'first_name' && in_array('last_name', $model->translatable ?? [], true)) {
                    $first = $this->translated($model, 'first_name', $locale, $locales) ?? '';
                    $last = $this->translated($model, 'last_name', $locale, $locales) ?? '';
                    $name = trim($first.' '.$last);

                    if ($name !== '') {
                        return $name;
                    }

                    continue;
                }

                if (! in_array($attribute, $model->translatable ?? [], true)) {
                    continue;
                }

                $label = $this->translated($model, $attribute, $locale, $locales);

                if (filled($label)) {
                    return $label;
                }
            }
        }

        foreach (['title', 'name', 'label'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_string($value) && filled($value)) {
                return $value;
            }
        }

        return (string) $model->getKey();
    }

    /**
     * @param  list<string>  $locales
     */
    private function translated(Model $model, string $attribute, string $locale, array $locales): ?string
    {
        if (! method_exists($model, 'getTranslation')) {
            return null;
        }

        $value = $model->getTranslation($attribute, $locale);

        if (filled($value)) {
            return $value;
        }

        foreach ($locales as $fallback) {
            $candidate = $model->getTranslation($attribute, $fallback, false);

            if (filled($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
