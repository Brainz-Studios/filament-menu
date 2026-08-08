<?php

namespace BrainzStudios\FilamentMenu\GraphQL\Resolvers;

use BrainzStudios\FilamentMenu\Models\MenuItem;
use BrainzStudios\FilamentMenu\Services\MenuPathBuilder;

final class ResolveMenuPath
{
    public function __construct(
        private MenuPathBuilder $menuPathBuilder,
    ) {}

    public function __invoke(MenuItem $menuItem): ?string
    {
        return $this->menuPathBuilder->pathForMenuItem($menuItem);
    }
}
