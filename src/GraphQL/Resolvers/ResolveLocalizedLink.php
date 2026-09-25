<?php

namespace BrainzStudios\FilamentMenu\GraphQL\Resolvers;

use BrainzStudios\FilamentMenu\Models\MenuItem;

final class ResolveLocalizedLink
{
    /**
     * @return array<string, ?string>
     */
    public function __invoke(MenuItem $menuItem): array
    {
        $translations = $menuItem->getTranslations('link');
        $result = [];

        foreach (config('filament-menu.locales', ['cs', 'en']) as $locale) {
            $value = $translations[$locale] ?? null;
            $result[$locale] = filled($value) ? (string) $value : null;
        }

        return $result;
    }
}
