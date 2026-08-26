@php
    use BrainzStudios\FilamentMenu\Models\MenuItem;

    $maxDepth = MenuItem::MAX_DEPTH;
    $flatItems = MenuItem::flattenTree($items);
@endphp

<div class="space-y-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('filament-menu::menu.tree_help_before') }}
            <span class="font-medium">← / →</span>
            {{ __('filament-menu::menu.tree_help_after') }}
        </p>

        @if (collect($flatItems)->contains(fn (array $item, int $index): bool => ($item['type'] ?? null) === MenuItem::TYPE_NODE
            && isset($flatItems[$index + 1])
            && (int) $flatItems[$index + 1]['depth'] > (int) $item['depth']))
            <div class="flex items-center gap-2 shrink-0">
                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    data-collapse-all
                >
                    {{ __('filament-menu::menu.collapse_all') }}
                </x-filament::button>
                <x-filament::button
                    type="button"
                    color="gray"
                    size="sm"
                    data-expand-all
                >
                    {{ __('filament-menu::menu.expand_all') }}
                </x-filament::button>
            </div>
        @endif
    </div>

    <div
        id="menu-tree-wrapper"
        class="space-y-2"
        wire:key="menu-tree-{{ md5(json_encode($flatItems)) }}"
    >
        @if (count($flatItems) === 0)
            <p class="text-gray-500 text-center py-8">
                {{ __('filament-menu::menu.empty') }}
            </p>
        @endif

        <ul
            id="menu-tree"
            class="space-y-1"
            data-max-depth="{{ $maxDepth }}"
            data-indent="24"
            data-depth-template="{{ __('filament-menu::menu.depth_badge', ['depth' => '__DEPTH__', 'max' => '__MAX__']) }}"
            data-collapse-label="{{ __('filament-menu::menu.collapse') }}"
            data-expand-label="{{ __('filament-menu::menu.expand') }}"
        >
            @foreach ($flatItems as $index => $item)
                @php
                    $previousDepth = $index > 0 ? (int) $flatItems[$index - 1]['depth'] : null;
                    $prospectiveParent = null;

                    if ($index > 0) {
                        $depth = (int) $item['depth'];

                        for ($i = $index - 1; $i >= 0; $i--) {
                            $candidateDepth = (int) $flatItems[$i]['depth'];

                            if ($candidateDepth < $depth) {
                                break;
                            }

                            if ($candidateDepth === $depth) {
                                $prospectiveParent = $flatItems[$i];
                                break;
                            }
                        }
                    }

                    $canIndent = $index > 0
                        && $item['depth'] <= $previousDepth
                        && $item['depth'] < $maxDepth
                        && ($prospectiveParent['type'] ?? null) === MenuItem::TYPE_NODE;

                    $nextDepth = isset($flatItems[$index + 1]) ? (int) $flatItems[$index + 1]['depth'] : 0;
                    $canCollapse = ($item['type'] ?? null) === MenuItem::TYPE_NODE
                        && $nextDepth > (int) $item['depth'];
                @endphp
                @include('filament-menu::forms.components.menu-tree-item', [
                    'item' => $item,
                    'depth' => $item['depth'],
                    'maxDepth' => $maxDepth,
                    'canIndent' => $canIndent,
                    'canCollapse' => $canCollapse,
                ])
            @endforeach
        </ul>
    </div>
</div>
