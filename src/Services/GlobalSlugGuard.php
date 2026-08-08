<?php

namespace BrainzStudios\FilamentMenu\Services;

use BrainzStudios\FilamentMenu\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class GlobalSlugGuard
{
    public const MENU_NODE_TYPE = 'menu_node';

    public function __construct(
        private LinkableRegistry $registry,
    ) {}

    /**
     * @return array<string, class-string<Model>>
     */
    public function contentTypes(): array
    {
        return $this->registry->models();
    }

    public function isTaken(
        string $slug,
        ?string $ignoreType = null,
        int|string|null $ignoreId = null,
    ): bool {
        return $this->conflict($slug, $ignoreType, $ignoreId) !== null;
    }

    /**
     * @return array{type: string, id: int|string}|null
     */
    public function conflict(
        string $slug,
        ?string $ignoreType = null,
        int|string|null $ignoreId = null,
    ): ?array {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $locales = $this->registry->locales();

        foreach ($this->contentTypes() as $type => $modelClass) {
            $query = $this->slugQuery($modelClass, $slug, $locales);

            if ($ignoreType === $type && $ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }

            $id = $query->value((new $modelClass)->getKeyName());

            if ($id !== null) {
                return ['type' => $type, 'id' => $id];
            }
        }

        $nodeQuery = MenuItem::query()
            ->where('type', MenuItem::TYPE_NODE)
            ->where(function (Builder $builder) use ($slug, $locales): void {
                foreach ($locales as $index => $locale) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $builder->{$method}('slug->'.$locale, $slug);
                }
            });

        if ($ignoreType === self::MENU_NODE_TYPE && $ignoreId !== null) {
            $nodeQuery->whereKeyNot($ignoreId);
        }

        $nodeId = $nodeQuery->value('id');

        if ($nodeId !== null) {
            return ['type' => self::MENU_NODE_TYPE, 'id' => $nodeId];
        }

        return null;
    }

    /**
     * @return Collection<string, list<array{type: string, id: int|string, locale: string}>>
     */
    public function duplicateIndex(): Collection
    {
        /** @var array<string, list<array{type: string, id: int|string, locale: string}>> $index */
        $index = [];
        $locales = $this->registry->locales();

        foreach ($this->contentTypes() as $type => $modelClass) {
            $modelClass::query()->toBase()->orderBy('id')->get(['id', 'slug'])->each(
                function (object $row) use (&$index, $type, $locales): void {
                    $this->collectRowSlugs($index, $type, $row->id, $row->slug, $locales);
                }
            );
        }

        MenuItem::query()
            ->where('type', MenuItem::TYPE_NODE)
            ->toBase()
            ->orderBy('id')
            ->get(['id', 'slug'])
            ->each(function (object $row) use (&$index, $locales): void {
                $this->collectRowSlugs($index, self::MENU_NODE_TYPE, $row->id, $row->slug, $locales);
            });

        return collect($index)
            ->map(fn (array $owners): array => $this->uniqueOwners($owners))
            ->filter(fn (array $owners): bool => count($owners) > 1);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  list<string>  $locales
     * @return Builder<Model>
     */
    private function slugQuery(string $modelClass, string $slug, array $locales): Builder
    {
        return $modelClass::query()
            ->where(function (Builder $builder) use ($slug, $locales): void {
                foreach ($locales as $index => $locale) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $builder->{$method}('slug->'.$locale, $slug);
                }
            });
    }

    /**
     * @param  array<string, list<array{type: string, id: int|string, locale: string}>>  $index
     * @param  list<string>  $locales
     */
    private function collectRowSlugs(array &$index, string $type, int|string $id, mixed $slugJson, array $locales): void
    {
        $translations = is_string($slugJson)
            ? (json_decode($slugJson, true) ?: [])
            : (is_array($slugJson) ? $slugJson : []);

        foreach ($locales as $locale) {
            $slug = trim((string) ($translations[$locale] ?? ''));

            if ($slug === '') {
                continue;
            }

            $index[$slug][] = [
                'type' => $type,
                'id' => $id,
                'locale' => $locale,
            ];
        }
    }

    /**
     * @param  list<array{type: string, id: int|string, locale: string}>  $owners
     * @return list<array{type: string, id: int|string, locale: string}>
     */
    private function uniqueOwners(array $owners): array
    {
        $seen = [];
        $unique = [];

        foreach ($owners as $owner) {
            $key = $owner['type'].':'.$owner['id'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $owner;
        }

        return $unique;
    }
}
