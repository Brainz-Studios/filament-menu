<?php

namespace BrainzStudios\FilamentMenu\Models;

use BrainzStudios\FilamentMenu\Database\Factories\MenuItemFactory;
use BrainzStudios\FilamentMenu\Models\Concerns\HasSeoAttributes;
use BrainzStudios\FilamentMenu\Services\MenuPathBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

#[Fillable(['parent_id', 'label', 'slug', 'type', 'link', 'target', 'icon', 'sort_order', 'is_published'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, HasSeoAttributes, HasTranslations, SoftDeletes;

    public const MAX_DEPTH = 7;

    public const TYPE_INTERNAL = 'internal';

    public const TYPE_EXTERNAL = 'external';

    public const TYPE_NODE = 'node';

    public array $translatable = ['label', 'slug'];

    protected static function newFactory(): MenuItemFactory
    {
        return MenuItemFactory::new();
    }

    /**
     * @return array{type: string, id: int}|null
     */
    public static function parseInternalLink(?string $link): ?array
    {
        if ($link === null || $link === '') {
            return null;
        }

        $types = collect(MenuPathBuilder::linkableTypes())
            ->sortByDesc(fn (string $type): int => strlen($type))
            ->implode('|');

        if ($types === '' || ! preg_match('/^('.$types.'):(\d+)$/', $link, $matches)) {
            return null;
        }

        return [
            'type' => $matches[1],
            'id' => (int) $matches[2],
        ];
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<MenuItem>  $query
     * @return Builder<MenuItem>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function depth(): int
    {
        $depth = 1;
        $parentId = $this->parent_id;

        while ($parentId !== null) {
            $depth++;
            $parentId = static::query()->whereKey($parentId)->value('parent_id');
        }

        return $depth;
    }

    public function subtreeDepth(): int
    {
        $maxChildDepth = 0;

        foreach ($this->children as $child) {
            $maxChildDepth = max($maxChildDepth, 1 + $child->subtreeDepth());
        }

        return $maxChildDepth;
    }

    /**
     * @param  Collection<int, MenuItem>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function nestCollection(Collection $items, ?int $parentId = null): array
    {
        return $items
            ->filter(fn (MenuItem $item): bool => $item->parent_id === $parentId)
            ->values()
            ->map(function (MenuItem $item) use ($items): array {
                $data = $item->toArray();
                $data['label'] = $item->getTranslations('label');
                $data['slug'] = $item->getTranslations('slug');
                $data['children'] = static::nestCollection($items, $item->id);

                return $data;
            })
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    public static function flattenTree(array $tree, int $depth = 1): array
    {
        $flat = [];

        foreach ($tree as $item) {
            $children = $item['children'] ?? [];
            unset($item['children']);
            $item['depth'] = $depth;
            $flat[] = $item;
            $flat = [...$flat, ...static::flattenTree($children, $depth + 1)];
        }

        return $flat;
    }

    /**
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = [...$ids, ...$child->descendantIds()];
        }

        return $ids;
    }

    public function isNode(): bool
    {
        return $this->type === static::TYPE_NODE;
    }

    public function canAcceptChild(): bool
    {
        return $this->isNode() && $this->depth() < static::MAX_DEPTH;
    }
}
