<?php

namespace BrainzStudios\FilamentMenu\GraphQL\Queries;

use BrainzStudios\FilamentMenu\Models\MenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

final class MenuItems
{
    /**
     * @param  array<string, mixed>  $args
     * @return Collection<int, MenuItem>
     */
    public function __invoke(null $_, array $args): Collection
    {
        return MenuItem::query()
            ->published()
            ->root()
            ->ordered()
            ->with(['children' => $this->eagerLoadPublishedChildren(MenuItem::MAX_DEPTH - 1)])
            ->get();
    }

    /**
     * @return \Closure(Builder<MenuItem>|Relation): void
     */
    private function eagerLoadPublishedChildren(int $remainingDepth): \Closure
    {
        return function (Builder|Relation $query) use ($remainingDepth): void {
            $query->published()->ordered();

            if ($remainingDepth > 1) {
                $query->with([
                    'children' => $this->eagerLoadPublishedChildren($remainingDepth - 1),
                ]);
            }
        };
    }
}
