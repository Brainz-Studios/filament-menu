<?php

namespace BrainzStudios\FilamentMenu\GraphQL\Resolvers;

use BrainzStudios\FilamentMenu\Models\MenuItem;
use BrainzStudios\FilamentMenu\Services\MenuPathBuilder;
use Illuminate\Database\Eloquent\Model;

final class ResolveMenuLinkable
{
    public function __construct(
        private MenuPathBuilder $menuPathBuilder,
    ) {}

    public function __invoke(MenuItem $menuItem): ?Model
    {
        if ($menuItem->type !== 'internal') {
            return null;
        }

        $parsed = MenuItem::parseInternalLink($menuItem->link);

        if ($parsed === null) {
            return null;
        }

        return $this->menuPathBuilder->resolveEntity($parsed['type'], $parsed['id'], publishedOnly: true);
    }
}
