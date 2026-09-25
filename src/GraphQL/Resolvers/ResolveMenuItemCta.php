<?php

namespace BrainzStudios\FilamentMenu\GraphQL\Resolvers;

use BrainzStudios\FilamentMenu\Models\MenuItem;
use BrainzStudios\FilamentMenu\Services\MenuPathBuilder;
use Illuminate\Database\Eloquent\Model;

final class ResolveMenuItemCta
{
    public function __construct(
        private MenuPathBuilder $menuPathBuilder,
    ) {}

    /**
     * @return array{type: string, link: string, url: ?string, target: string, linkable: ?Model}|null
     */
    public function __invoke(MenuItem $menuItem): ?array
    {
        if ($menuItem->type !== MenuItem::TYPE_NODE) {
            return null;
        }

        $type = $menuItem->cta_type;

        if (! in_array($type, [MenuItem::TYPE_INTERNAL, MenuItem::TYPE_EXTERNAL], true)) {
            return null;
        }

        $link = $menuItem->localizedCtaLink();

        if ($link === null) {
            return null;
        }

        $target = $menuItem->cta_target === '_blank' ? '_blank' : '_self';

        if ($type === MenuItem::TYPE_EXTERNAL) {
            return [
                'type' => MenuItem::TYPE_EXTERNAL,
                'link' => $link,
                'url' => $link,
                'target' => $target,
                'linkable' => null,
            ];
        }

        $parsed = MenuItem::parseInternalLink($link);

        if ($parsed === null) {
            return null;
        }

        $entity = $this->menuPathBuilder->resolveEntity($parsed['type'], $parsed['id'], publishedOnly: true);

        if ($entity === null) {
            return null;
        }

        return [
            'type' => MenuItem::TYPE_INTERNAL,
            'link' => $link,
            'url' => $this->menuPathBuilder->pathForEntity($entity),
            'target' => $target,
            'linkable' => $entity,
        ];
    }
}
