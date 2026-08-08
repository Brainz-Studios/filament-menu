@php
    $maxDepth = $maxDepth ?? \BrainzStudios\FilamentMenu\Models\MenuItem::MAX_DEPTH;
    $canIndent = $canIndent ?? false;
    $label = is_array($item['label'] ?? null)
        ? ($item['label']['cs'] ?? $item['label']['en'] ?? '')
        : (string) ($item['label'] ?? '');
    $type = $item['type'] ?? '';
    $canHaveChildren = $type === \BrainzStudios\FilamentMenu\Models\MenuItem::TYPE_NODE && $depth < $maxDepth;
    $canOutdent = $depth > 1;
@endphp

<li
    class="menu-item"
    data-id="{{ $item['id'] }}"
    data-depth="{{ $depth }}"
    data-type="{{ $type }}"
    data-can-accept-children="{{ $type === \BrainzStudios\FilamentMenu\Models\MenuItem::TYPE_NODE ? '1' : '0' }}"
    style="margin-left: {{ ($depth - 1) * 24 }}px"
>
    <div class="menu-item-handle flex items-center justify-between gap-2 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-transparent hover:border-gray-300 dark:hover:border-gray-600">
        <div class="flex items-center gap-3 min-w-0 cursor-move">
            <span class="text-gray-400 shrink-0 select-none" aria-hidden="true">☰</span>
            <div class="flex items-center gap-2 min-w-0 flex-wrap">
                <span class="font-medium truncate">{{ $label }}</span>
                @if ($type === \BrainzStudios\FilamentMenu\Models\MenuItem::TYPE_INTERNAL)
                    <span class="text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 shrink-0">
                        {{ __('filament-menu::menu.fields.link_type_options.internal') }}
                    </span>
                @elseif ($type === \BrainzStudios\FilamentMenu\Models\MenuItem::TYPE_EXTERNAL)
                    <span class="text-xs px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 shrink-0">
                        {{ __('filament-menu::menu.fields.link_type_options.external') }}
                    </span>
                @elseif ($type === \BrainzStudios\FilamentMenu\Models\MenuItem::TYPE_NODE)
                    <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 shrink-0">
                        {{ __('filament-menu::menu.fields.link_type_options.node') }}
                    </span>
                @endif
                @if (filled($item['link'] ?? null))
                    <span class="text-xs text-gray-400 truncate">{{ $item['link'] }}</span>
                @endif
                <span
                    data-depth-badge
                    class="text-xs text-primary-600 dark:text-primary-400 shrink-0 font-medium"
                >{{ __('filament-menu::menu.depth_badge', ['depth' => $depth, 'max' => $maxDepth]) }}</span>
            </div>
        </div>
        <div class="flex items-center gap-1 shrink-0">
            @unless ($item['is_published'] ?? true)
                <span class="text-xs text-warning-600 bg-warning-100 dark:bg-warning-900 dark:text-warning-300 px-2 py-0.5 rounded">
                    {{ __('filament-menu::menu.hidden') }}
                </span>
            @endunless

            <x-filament::icon-button
                icon="heroicon-o-arrow-left"
                color="gray"
                wire:click="outdentItem({{ $item['id'] }})"
                size="xs"
                :tooltip="__('filament-menu::menu.outdent')"
                :disabled="! $canOutdent"
            />

            <x-filament::icon-button
                icon="heroicon-o-arrow-right"
                color="gray"
                wire:click="indentItem({{ $item['id'] }})"
                size="xs"
                :tooltip="__('filament-menu::menu.indent')"
                :disabled="! $canIndent"
            />

            <span @class(['hidden' => ! $canHaveChildren]) data-add-child>
                <x-filament::icon-button
                    icon="heroicon-o-plus"
                    color="gray"
                    wire:click="addChild({{ $item['id'] }})"
                    size="xs"
                    :tooltip="__('filament-menu::menu.add_child')"
                />
            </span>

            <x-filament::icon-button
                icon="heroicon-o-pencil"
                color="gray"
                wire:click="startEdit({{ $item['id'] }})"
                size="xs"
                :tooltip="__('filament-menu::menu.edit')"
            />

            <x-filament::icon-button
                icon="heroicon-o-trash"
                color="danger"
                wire:click="deleteItem({{ $item['id'] }})"
                wire:confirm="{{ __('filament-menu::menu.delete_confirm') }}"
                size="xs"
                :tooltip="__('filament-menu::menu.delete')"
            />
        </div>
    </div>
</li>
