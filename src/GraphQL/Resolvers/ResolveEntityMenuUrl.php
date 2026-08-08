<?php

namespace BrainzStudios\FilamentMenu\GraphQL\Resolvers;

use BrainzStudios\FilamentMenu\Services\MenuPathBuilder;
use Illuminate\Database\Eloquent\Model;

final class ResolveEntityMenuUrl
{
    public function __construct(
        private MenuPathBuilder $menuPathBuilder,
    ) {}

    public function __invoke(Model $root): ?string
    {
        return $this->menuPathBuilder->pathForEntity($root);
    }
}
