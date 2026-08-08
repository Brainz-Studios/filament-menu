<?php

namespace BrainzStudios\FilamentMenu\GraphQL\Resolvers;

use BrainzStudios\FilamentMenu\Services\MenuPathBuilder;
use Illuminate\Database\Eloquent\Model;

final class ResolveEntityBreadcrumbs
{
    public function __construct(
        private MenuPathBuilder $menuPathBuilder,
    ) {}

    /**
     * @return array{
     *     cs: list<array{label: string, menu_url: ?string}>,
     *     en: list<array{label: string, menu_url: ?string}>
     * }
     */
    public function __invoke(Model $root): array
    {
        return $this->menuPathBuilder->breadcrumbsForEntity($root);
    }
}
