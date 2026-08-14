<?php

namespace BrainzStudios\FilamentMenu\Pages;

use BrainzStudios\FilamentMenu\Forms\Components\SeoSection;
use BrainzStudios\FilamentMenu\Forms\Components\TranslatableTabs;
use BrainzStudios\FilamentMenu\Support\AutoSlug;
use BrainzStudios\FilamentMenu\Models\MenuItem;
use BrainzStudios\FilamentMenu\Services\GlobalSlugGuard;
use BrainzStudios\FilamentMenu\Services\LinkableRegistry;
use BrainzStudios\FilamentMenu\Services\MenuPathBuilder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ManageMenu extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament-menu::pages.manage-menu';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return config('filament-menu.navigation.icon', parent::getNavigationIcon());
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-menu.navigation.sort', parent::getNavigationSort());
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-menu::menu.navigation_label');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-menu.navigation.group');
    }

    public function getTitle(): string|Htmlable
    {
        return __('filament-menu::menu.title');
    }

    public array $menuTree = [];

    public ?array $data = [];

    public bool $editing = false;

    public ?int $editItemId = null;

    public function mount(): void
    {
        $this->form->fill([
            'type' => 'internal',
            'linkable_type' => null,
            'target' => '_self',
            'is_published' => true,
        ]);
        $this->loadTree();
    }

    public function loadTree(): void
    {
        $items = MenuItem::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $this->menuTree = MenuItem::nestCollection($items);

        $this->dispatch('menu-tree-updated');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->schema([
                Select::make('parent_id')
                    ->label(__('filament-menu::menu.fields.parent'))
                    ->options(fn (): array => $this->parentOptions())
                    ->searchable()
                    ->nullable(),
                TranslatableTabs::make($this->labelSlugTabs()),
                SeoSection::make(),
                Select::make('type')
                    ->label(__('filament-menu::menu.fields.link_type'))
                    ->options([
                        MenuItem::TYPE_INTERNAL => __('filament-menu::menu.fields.link_type_options.internal'),
                        MenuItem::TYPE_EXTERNAL => __('filament-menu::menu.fields.link_type_options.external'),
                        MenuItem::TYPE_NODE => __('filament-menu::menu.fields.link_type_options.node'),
                    ])
                    ->default(MenuItem::TYPE_INTERNAL)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('linkable_type', null);
                        $set('link', null);
                    }),
                Select::make('linkable_type')
                    ->label(__('filament-menu::menu.fields.linkable_type'))
                    ->options(fn (): array => $this->linkableTypeOptions())
                    ->required()
                    ->live()
                    ->visible(fn (Get $get): bool => $get('type') === MenuItem::TYPE_INTERNAL)
                    ->dehydrated(false)
                    ->afterStateUpdated(function (Set $set): void {
                        $set('link', null);
                    }),
                Select::make('link')
                    ->label(__('filament-menu::menu.fields.link_target'))
                    ->options(fn (Get $get): array => $this->internalLinkOptions(
                        is_string($get('linkable_type')) ? $get('linkable_type') : null,
                    ))
                    ->searchable()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('type') === MenuItem::TYPE_INTERNAL && filled($get('linkable_type')))
                    ->dehydrated(fn (Get $get): bool => $get('type') === MenuItem::TYPE_INTERNAL),
                TextInput::make('link')
                    ->label(__('filament-menu::menu.fields.url'))
                    ->url()
                    ->required()
                    ->placeholder('https://example.com')
                    ->visible(fn (Get $get): bool => $get('type') === MenuItem::TYPE_EXTERNAL)
                    ->dehydrated(fn (Get $get): bool => $get('type') === MenuItem::TYPE_EXTERNAL),
                Select::make('target')
                    ->label(__('filament-menu::menu.fields.target_window'))
                    ->options([
                        '_self' => __('filament-menu::menu.fields.target_options._self'),
                        '_blank' => __('filament-menu::menu.fields.target_options._blank'),
                    ])
                    ->default('_self')
                    ->visible(fn (Get $get): bool => in_array($get('type'), [MenuItem::TYPE_INTERNAL, MenuItem::TYPE_EXTERNAL], true)),
                Toggle::make('is_published')
                    ->label(__('filament-menu::menu.fields.is_published'))
                    ->default(true),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if ($this->editing) {
            $this->update();
        } else {
            $this->create();
        }
    }

    public function create(): void
    {
        $data = $this->normalizeMenuItemData($this->form->getState());
        $this->assertParentAllowsChild($data['parent_id'] ?? null);
        $this->assertInternalLinkIsUnique($data);
        $this->assertNodeSlugIsGloballyUnique($data);

        $data['sort_order'] = MenuItem::query()
            ->where('parent_id', $data['parent_id'] ?? null)
            ->max('sort_order') + 1;

        MenuItem::create($data);

        Notification::make()
            ->title(__('filament-menu::menu.notifications.created'))
            ->success()
            ->send();

        $this->resetForm();
        $this->loadTree();
    }

    public function startEdit(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $this->editItemId = $id;
        $this->editing = true;

        $this->form->fill([
            'parent_id' => $item->parent_id,
            'label' => $item->getTranslations('label'),
            'slug' => $item->getTranslations('slug'),
            'seo_title' => $item->getTranslations('seo_title'),
            'seo_description' => $item->getTranslations('seo_description'),
            'seo_keywords' => $item->getTranslations('seo_keywords'),
            'seo_canonical_url' => $item->getTranslations('seo_canonical_url'),
            'seo_og_title' => $item->getTranslations('seo_og_title'),
            'seo_og_description' => $item->getTranslations('seo_og_description'),
            'seo_image' => $item->seo_image,
            'seo_robots' => $item->seo_robots?->value,
            'seo_og_type' => $item->seo_og_type?->value,
            'seo_twitter_card' => $item->seo_twitter_card?->value,
            'type' => $item->type,
            'linkable_type' => MenuItem::parseInternalLink($item->link)['type'] ?? null,
            'link' => $item->link,
            'target' => $item->target,
            'is_published' => $item->is_published,
        ]);
    }

    public function update(): void
    {
        $data = $this->normalizeMenuItemData($this->form->getState());
        $item = MenuItem::findOrFail($this->editItemId);

        $parentId = $data['parent_id'] ?? null;

        if ($parentId === $item->id) {
            throw ValidationException::withMessages([
                'data.parent_id' => __('filament-menu::menu.errors.cannot_be_own_parent'),
            ]);
        }

        if ($parentId !== null && in_array($parentId, $item->descendantIds(), true)) {
            throw ValidationException::withMessages([
                'data.parent_id' => __('filament-menu::menu.errors.cannot_nest_under_descendant'),
            ]);
        }

        $this->assertCanBecomeNonNode($item, $data['type'] ?? null);
        $this->assertParentAllowsChild($parentId, $item);
        $this->assertInternalLinkIsUnique($data, $item->id);
        $this->assertNodeSlugIsGloballyUnique($data, $item->id);

        $item->update($data);

        Notification::make()
            ->title(__('filament-menu::menu.notifications.updated'))
            ->success()
            ->send();

        $this->resetForm();
        $this->loadTree();
    }

    public function deleteItem(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $item->delete();

        Notification::make()
            ->title(__('filament-menu::menu.notifications.deleted'))
            ->success()
            ->send();

        $this->resetForm();
        $this->loadTree();
    }

    public function addChild(int $parentId): void
    {
        $parent = MenuItem::findOrFail($parentId);

        if (! $parent->canAcceptChild()) {
            Notification::make()
                ->title(__('filament-menu::menu.errors.parent_must_be_node'))
                ->danger()
                ->send();

            return;
        }

        $this->editing = false;
        $this->editItemId = null;

        $this->form->fill([
            'parent_id' => $parentId,
            'type' => 'internal',
            'linkable_type' => null,
            'target' => '_self',
            'is_published' => true,
        ]);
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function indentItem(int $id): void
    {
        $flat = MenuItem::flattenTree($this->menuTree);
        $index = collect($flat)->search(fn (array $item): bool => (int) $item['id'] === $id);

        if ($index === false || $index === 0) {
            return;
        }

        $item = $flat[$index];
        $previous = $flat[$index - 1];
        $prospectiveParent = $this->prospectiveParentAfterIndent($flat, $index);

        if (
            $item['depth'] > $previous['depth']
            || $item['depth'] >= MenuItem::MAX_DEPTH
            || $prospectiveParent === null
            || ($prospectiveParent['type'] ?? null) !== MenuItem::TYPE_NODE
        ) {
            Notification::make()
                ->title(__('filament-menu::menu.errors.cannot_indent'))
                ->warning()
                ->send();

            return;
        }

        $flat = $this->shiftBlockDepth($flat, $index, 1);
        $this->persistFlatOrder($flat);
    }

    public function outdentItem(int $id): void
    {
        $flat = MenuItem::flattenTree($this->menuTree);
        $index = collect($flat)->search(fn (array $item): bool => (int) $item['id'] === $id);

        if ($index === false) {
            return;
        }

        $item = $flat[$index];

        if ($item['depth'] <= 1) {
            return;
        }

        $flat = $this->shiftBlockDepth($flat, $index, -1);
        $this->persistFlatOrder($flat);
    }

    /**
     * @param  array<int, array<string, mixed>>  $flat
     * @return array<int, array<string, mixed>>
     */
    protected function shiftBlockDepth(array $flat, int $index, int $delta): array
    {
        $rootDepth = (int) $flat[$index]['depth'];
        $end = $index;

        for ($i = $index + 1; $i < count($flat); $i++) {
            if ((int) $flat[$i]['depth'] <= $rootDepth) {
                break;
            }

            $end = $i;
        }

        for ($i = $index; $i <= $end; $i++) {
            $flat[$i]['depth'] = max(1, min(MenuItem::MAX_DEPTH, (int) $flat[$i]['depth'] + $delta));
        }

        return $flat;
    }

    /**
     * @param  array<int, array<string, mixed>>  $flat
     */
    protected function persistFlatOrder(array $flat): void
    {
        $this->saveOrder($this->flatToTree($flat));
    }

    /**
     * @param  array<int, array<string, mixed>>  $flat
     * @return array<int, array{id: int, children: array<int, mixed>}>
     */
    protected function flatToTree(array $flat): array
    {
        $ids = [];
        $parentIds = [];
        $stack = [];

        foreach ($flat as $item) {
            $id = (int) $item['id'];
            $depth = (int) $item['depth'];

            while ($stack !== [] && $stack[array_key_last($stack)]['depth'] >= $depth) {
                array_pop($stack);
            }

            $parentIds[$id] = $stack === [] ? null : $stack[array_key_last($stack)]['id'];
            $ids[] = $id;
            $stack[] = ['depth' => $depth, 'id' => $id];
        }

        $build = function (?int $parentId) use (&$build, $ids, $parentIds): array {
            $children = [];

            foreach ($ids as $id) {
                if ($parentIds[$id] === $parentId) {
                    $children[] = [
                        'id' => $id,
                        'children' => $build($id),
                    ];
                }
            }

            return $children;
        };

        return $build(null);
    }

    public function saveOrder(array $order): void
    {
        if ($this->orderExceedsMaxDepth($order)) {
            Notification::make()
                ->title(__('filament-menu::menu.errors.max_depth', ['max' => MenuItem::MAX_DEPTH]))
                ->danger()
                ->send();

            $this->loadTree();

            return;
        }

        $order = $this->sanitizeOrderParents($order);

        if ($this->orderExceedsMaxDepth($order)) {
            Notification::make()
                ->title(__('filament-menu::menu.errors.max_depth', ['max' => MenuItem::MAX_DEPTH]))
                ->danger()
                ->send();

            $this->loadTree();

            return;
        }

        DB::transaction(function () use ($order): void {
            foreach ($order as $index => $itemData) {
                $this->updateOrderRecursive($itemData, $index, null, 1);
            }
        });

        $this->loadTree();
    }

    /**
     * @param  array<int, array{id: int, children?: array<int, array<string, mixed>>}>  $order
     */
    protected function orderExceedsMaxDepth(array $order, int $depth = 1): bool
    {
        foreach ($order as $itemData) {
            if ($depth > MenuItem::MAX_DEPTH) {
                return true;
            }

            if ($this->orderExceedsMaxDepth($itemData['children'] ?? [], $depth + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{id: int, children?: array<int, array<string, mixed>>}  $itemData
     */
    protected function updateOrderRecursive(array $itemData, int $index, ?int $parentId, int $depth): void
    {
        MenuItem::whereKey($itemData['id'])->update([
            'sort_order' => $index,
            'parent_id' => $parentId,
        ]);

        foreach ($itemData['children'] ?? [] as $childIndex => $childData) {
            $this->updateOrderRecursive($childData, $childIndex, $itemData['id'], $depth + 1);
        }
    }

    protected function resetForm(): void
    {
        $this->editItemId = null;
        $this->editing = false;

        $this->form->fill([
            'type' => 'internal',
            'target' => '_self',
            'is_published' => true,
            'parent_id' => null,
            'label' => ['cs' => '', 'en' => ''],
            'linkable_type' => null,
            'link' => null,
        ]);
    }

    /**
     * @return array<int|string, string>
     */
    protected function parentOptions(): array
    {
        $items = MenuItem::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $tree = MenuItem::nestCollection($items);
        $excludeIds = [];

        if ($this->editItemId !== null) {
            $editing = $items->firstWhere('id', $this->editItemId);

            if ($editing instanceof MenuItem) {
                $excludeIds = [$editing->id, ...$editing->descendantIds()];
            }
        }

        return $this->flattenParentOptions($tree, $excludeIds);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  list<int>  $excludeIds
     * @return array<int, string>
     */
    protected function flattenParentOptions(array $items, array $excludeIds, int $depth = 1, string $prefix = ''): array
    {
        $options = [];

        foreach ($items as $item) {
            if (in_array($item['id'], $excludeIds, true)) {
                continue;
            }

            if ($depth >= MenuItem::MAX_DEPTH) {
                continue;
            }

            if (($item['type'] ?? null) !== MenuItem::TYPE_NODE) {
                $options += $this->flattenParentOptions(
                    $item['children'] ?? [],
                    $excludeIds,
                    $depth + 1,
                    $prefix.'— ',
                );

                continue;
            }

            $label = is_array($item['label'] ?? null)
                ? ($item['label']['cs'] ?? $item['label']['en'] ?? '')
                : (string) ($item['label'] ?? '');

            $options[$item['id']] = $prefix.$label;

            $options += $this->flattenParentOptions(
                $item['children'] ?? [],
                $excludeIds,
                $depth + 1,
                $prefix.'— ',
            );
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected function linkableTypeOptions(): array
    {
        $registry = app(LinkableRegistry::class);
        $options = [];

        foreach ($registry->types() as $type) {
            $options[$type] = $registry->labelFor($type);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    protected function internalLinkOptions(?string $type = null): array
    {
        if (! filled($type)) {
            return [];
        }

        $usedLinks = MenuItem::query()
            ->where('type', 'internal')
            ->when($this->editItemId !== null, fn ($query) => $query->whereKeyNot($this->editItemId))
            ->pluck('link')
            ->all();

        return app(MenuPathBuilder::class)->internalLinkOptions($usedLinks, onlyType: $type);
    }

    /**
     * @param  array<string, mixed>  $data
     */

    /**
     * @return array<string, list<mixed>>
     */
    protected function labelSlugTabs(): array
    {
        $tabs = [];

        foreach (config('filament-menu.locales', ['cs', 'en']) as $locale) {
            $tabs[$locale] = [
                TextInput::make("label.{$locale}")
                    ->label(__('filament-menu::menu.fields.label'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(AutoSlug::fromLocalizedField($locale)),
                TextInput::make("slug.{$locale}")
                    ->label(__('filament-menu::menu.fields.slug')),
            ];
        }

        return $tabs;
    }

    protected function assertNodeSlugIsGloballyUnique(array $data, ?int $ignoreId = null): void
    {
        if (($data['type'] ?? null) !== MenuItem::TYPE_NODE) {
            return;
        }

        $slug = $data['slug'] ?? [];
        $guard = app(GlobalSlugGuard::class);
        $messages = [];

        foreach (config('filament-menu.locales', ['cs', 'en']) as $locale) {
            $value = is_array($slug) ? trim((string) ($slug[$locale] ?? '')) : '';

            if ($value === '') {
                continue;
            }

            if ($guard->isTaken($value, GlobalSlugGuard::MENU_NODE_TYPE, $ignoreId)) {
                $messages["data.slug.{$locale}"] = __('filament-menu::menu.validation.unique_content_slug');
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function assertInternalLinkIsUnique(array $data, ?int $ignoreId = null): void
    {
        if (($data['type'] ?? null) !== 'internal') {
            return;
        }

        $link = $data['link'] ?? null;

        if (! is_string($link) || $link === '') {
            return;
        }

        $exists = MenuItem::query()
            ->where('type', 'internal')
            ->where('link', $link)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'data.link' => __('filament-menu::menu.errors.link_already_used'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeMenuItemData(array $data): array
    {
        if (($data['type'] ?? null) === MenuItem::TYPE_NODE) {
            $data['link'] = '';
            $data['target'] = '_self';
        }

        $label = $data['label'] ?? [];
        $slug = $data['slug'] ?? [];

        foreach (config('filament-menu.locales', ['cs', 'en']) as $locale) {
            $labelValue = is_array($label) ? (string) ($label[$locale] ?? '') : '';
            $slugValue = is_array($slug) ? (string) ($slug[$locale] ?? '') : '';

            if ($slugValue === '' && $labelValue !== '') {
                $slug[$locale] = AutoSlug::make($labelValue);
            }
        }

        $data['slug'] = $slug;

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $flat
     * @return array<string, mixed>|null
     */
    protected function prospectiveParentAfterIndent(array $flat, int $index): ?array
    {
        $depth = (int) $flat[$index]['depth'];

        for ($i = $index - 1; $i >= 0; $i--) {
            $candidateDepth = (int) $flat[$i]['depth'];

            if ($candidateDepth < $depth) {
                return null;
            }

            if ($candidateDepth === $depth) {
                return $flat[$i];
            }
        }

        return null;
    }

    /**
     * Promote children of non-node parents so drag/reorder is never blocked by legacy nesting.
     *
     * @param  array<int, array{id: int, children?: array<int, array<string, mixed>>}>  $order
     * @return array<int, array{id: int, children: array<int, array<string, mixed>>}>
     */
    protected function sanitizeOrderParents(array $order): array
    {
        $result = [];

        foreach ($order as $itemData) {
            $id = (int) $itemData['id'];
            $children = $this->sanitizeOrderParents($itemData['children'] ?? []);
            $parent = MenuItem::query()->find($id);

            if ($parent?->isNode()) {
                $result[] = [
                    'id' => $id,
                    'children' => $children,
                ];

                continue;
            }

            $result[] = [
                'id' => $id,
                'children' => [],
            ];

            foreach ($children as $child) {
                $result[] = $child;
            }
        }

        return $result;
    }

    protected function assertCanBecomeNonNode(MenuItem $item, ?string $type): void
    {
        if ($type === MenuItem::TYPE_NODE || $type === $item->type) {
            return;
        }

        if ($item->isNode() && $item->children()->exists()) {
            throw ValidationException::withMessages([
                'data.type' => __('filament-menu::menu.errors.node_has_children'),
            ]);
        }
    }

    protected function assertParentAllowsChild(?int $parentId, ?MenuItem $item = null): void
    {
        if ($parentId === null) {
            if ($item !== null && 1 + $item->subtreeDepth() > MenuItem::MAX_DEPTH) {
                throw ValidationException::withMessages([
                    'data.parent_id' => __('filament-menu::menu.errors.max_depth', ['max' => MenuItem::MAX_DEPTH]),
                ]);
            }

            return;
        }

        $parent = MenuItem::findOrFail($parentId);

        if (! $parent->isNode()) {
            throw ValidationException::withMessages([
                'data.parent_id' => __('filament-menu::menu.errors.parent_must_be_node'),
            ]);
        }

        $parentDepth = $parent->depth();
        $subtreeDepth = $item?->subtreeDepth() ?? 0;

        if ($parentDepth + 1 + $subtreeDepth > MenuItem::MAX_DEPTH) {
            throw ValidationException::withMessages([
                'data.parent_id' => __('filament-menu::menu.errors.max_depth', ['max' => MenuItem::MAX_DEPTH]),
            ]);
        }
    }
}
