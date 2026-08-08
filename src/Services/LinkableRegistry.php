<?php

namespace BrainzStudios\FilamentMenu\Services;

use Illuminate\Database\Eloquent\Model;

final class LinkableRegistry
{
    /**
     * @return array<string, class-string<Model>>
     */
    public function models(): array
    {
        /** @var array<string, class-string<Model>> $linkables */
        $linkables = config('filament-menu.linkables', []);

        return $linkables;
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->models());
    }

    /**
     * @return class-string<Model>|null
     */
    public function modelFor(string $type): ?string
    {
        return $this->models()[$type] ?? null;
    }

    public function typeFor(Model $entity): ?string
    {
        foreach ($this->models() as $type => $modelClass) {
            if ($entity instanceof $modelClass) {
                return $type;
            }
        }

        return null;
    }

    public function graphqlTypeName(string $type): ?string
    {
        $modelClass = $this->modelFor($type);

        return $modelClass !== null ? class_basename($modelClass) : null;
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        /** @var list<string> $locales */
        $locales = config('filament-menu.locales', ['cs', 'en']);

        return $locales;
    }
}
