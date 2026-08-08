<?php

namespace BrainzStudios\FilamentMenu\GraphQL\Unions;

use BrainzStudios\FilamentMenu\Services\MenuPathBuilder;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

final class MenuLinkable
{
    public function __construct(
        private TypeRegistry $typeRegistry,
    ) {}

    public function __invoke(mixed $root, GraphQLContext $context, ResolveInfo $resolveInfo): Type
    {
        if (! $root instanceof Model) {
            throw new \RuntimeException('Unknown MenuLinkable type ['.gettype($root).'].');
        }

        $type = app(MenuPathBuilder::class)->entityType($root);
        $typeName = $type !== null ? MenuPathBuilder::graphqlTypeName($type) : null;

        if ($typeName === null) {
            throw new \RuntimeException('Unknown MenuLinkable type ['.$root::class.'].');
        }

        return $this->typeRegistry->get($typeName);
    }
}
