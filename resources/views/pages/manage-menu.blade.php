<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-filament::section :heading="__('filament-menu::menu.tree')">
                @include('filament-menu::forms.components.menu-tree', ['items' => $menuTree])
            </x-filament::section>
        </div>

        <div>
            <x-filament::section :heading="$editing ? __('filament-menu::menu.edit_item') : __('filament-menu::menu.new_item')">
                <form wire:submit="save" class="space-y-4">
                    {{ $this->form }}

                    <div class="flex gap-2">
                        <x-filament::button type="submit" color="primary">
                            {{ $editing ? __('filament-menu::menu.save_changes') : __('filament-menu::menu.add_item') }}
                        </x-filament::button>

                        <x-filament::button
                            x-show="$wire.editing"
                            type="button"
                            color="gray"
                            wire:click="cancelEdit"
                        >
                            {{ __('filament-menu::menu.cancel') }}
                        </x-filament::button>
                    </div>
                </form>
            </x-filament::section>
        </div>
    </div>

    @assets
        <script src="{{ asset('vendor/filament-menu/Sortable.min.js') }}"></script>
    @endassets

    @script
    <script>
        let sortable = null;
        let dragBlock = [];
        let startX = 0;
        let currentX = 0;

        const indentSize = () => Number(document.getElementById('menu-tree')?.dataset.indent || 24);
        const maxDepth = () => Number(document.getElementById('menu-tree')?.dataset.maxDepth || 7);
        const depthTemplate = () => document.getElementById('menu-tree')?.dataset.depthTemplate || ':depth/:max';
        const collapseLabel = () => document.getElementById('menu-tree')?.dataset.collapseLabel || '';
        const expandLabel = () => document.getElementById('menu-tree')?.dataset.expandLabel || '';
        const menuItems = (tree) => Array.from(tree.querySelectorAll(':scope > li.menu-item'));
        const collapsedStorageKey = 'filament-menu-collapsed-nodes';

        const canAcceptChildren = (li) => li?.dataset.canAcceptChildren === '1';

        const loadCollapsedIds = () => {
            try {
                return new Set((JSON.parse(localStorage.getItem(collapsedStorageKey) || '[]') || []).map(String));
            } catch {
                return new Set();
            }
        };

        const saveCollapsedIds = (ids) => {
            localStorage.setItem(collapsedStorageKey, JSON.stringify([...ids]));
        };

        const updateCollapseToggle = (li, collapsed) => {
            const toggle = li.querySelector('[data-collapse-toggle]');

            if (! toggle) {
                return;
            }

            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('title', collapsed ? expandLabel() : collapseLabel());

            const chevron = toggle.querySelector('[data-collapse-chevron]') ?? toggle.querySelector('svg');
            chevron?.classList.toggle('-rotate-90', collapsed);
        };

        const applyCollapsed = (tree) => {
            const collapsedIds = loadCollapsedIds();
            const items = menuItems(tree);
            let hideBelowDepth = null;

            items.forEach((li) => {
                const depth = Number(li.dataset.depth || 1);
                const id = String(li.dataset.id || '');
                const collapsed = collapsedIds.has(id);

                if (hideBelowDepth !== null && depth > hideBelowDepth) {
                    li.hidden = true;
                    updateCollapseToggle(li, collapsed);

                    return;
                }

                hideBelowDepth = collapsed ? depth : null;
                li.hidden = false;
                updateCollapseToggle(li, collapsed);
            });
        };

        const depthLabel = (depth) => depthTemplate()
            .replaceAll('__DEPTH__', String(depth))
            .replaceAll('__MAX__', String(maxDepth()));

        const applyDepthStyles = (li, depth) => {
            li.dataset.depth = String(depth);
            li.style.marginLeft = `${(depth - 1) * indentSize()}px`;

            const badge = li.querySelector('[data-depth-badge]');
            if (badge) {
                badge.textContent = depthLabel(depth);
            }

            const addButton = li.querySelector('[data-add-child]');
            if (addButton) {
                addButton.classList.toggle('hidden', depth >= maxDepth() || ! canAcceptChildren(li));
            }
        };

        const setBlockDepths = (rootDepth) => {
            if (! dragBlock.length) {
                return;
            }

            const originalRootDepth = Number(dragBlock[0].dataset.originalDepth);
            const delta = rootDepth - originalRootDepth;
            let deepest = 0;

            dragBlock.forEach((el) => {
                const nextDepth = Number(el.dataset.originalDepth) + delta;
                deepest = Math.max(deepest, nextDepth);
                applyDepthStyles(el, nextDepth);
            });

            if (deepest > maxDepth()) {
                const overflow = deepest - maxDepth();
                dragBlock.forEach((el) => {
                    applyDepthStyles(el, Number(el.dataset.depth) - overflow);
                });
            }
        };

        const proposedRootDepth = (item) => {
            const previous = item.previousElementSibling;
            const originalRootDepth = Number(item.dataset.originalDepth || item.dataset.depth || 1);
            const deltaLevels = Math.round((currentX - startX) / indentSize());
            let depth = originalRootDepth + deltaLevels;

            depth = Math.max(1, Math.min(maxDepth(), depth));

            if (! previous) {
                return 1;
            }

            const previousDepth = Number(previous.dataset.depth || 1);
            const maxAllowed = canAcceptChildren(previous) ? previousDepth + 1 : previousDepth;

            return Math.min(depth, maxAllowed);
        };

        const collectBlock = (li) => {
            const rootDepth = Number(li.dataset.depth || 1);
            const block = [li];
            let next = li.nextElementSibling;

            while (next && Number(next.dataset.depth || 1) > rootDepth) {
                block.push(next);
                next = next.nextElementSibling;
            }

            return block;
        };

        const restoreBlockAfter = (item) => {
            let anchor = item;
            dragBlock.slice(1).forEach((el) => {
                anchor.after(el);
                anchor = el;
            });
        };

        const buildTreeFromFlat = (tree) => {
            const items = menuItems(tree).map((li) => ({
                id: Number(li.dataset.id),
                depth: Number(li.dataset.depth || 1),
                children: [],
            }));

            const root = [];
            const stack = [];

            items.forEach((item) => {
                while (stack.length && stack[stack.length - 1].depth >= item.depth) {
                    stack.pop();
                }

                if (! stack.length) {
                    root.push(item);
                } else {
                    stack[stack.length - 1].children.push(item);
                }

                stack.push(item);
            });

            const stripDepth = (nodes) => nodes.map((node) => ({
                id: node.id,
                children: stripDepth(node.children),
            }));

            return stripDepth(root);
        };

        const normalizeDepths = (tree) => {
            const items = menuItems(tree);

            items.forEach((li, index) => {
                let depth = Number(li.dataset.depth || 1);

                if (index === 0) {
                    depth = 1;
                } else {
                    const previous = items[index - 1];
                    const previousDepth = Number(previous.dataset.depth || 1);
                    const maxChildDepth = canAcceptChildren(previous)
                        ? previousDepth + 1
                        : previousDepth;

                    depth = Math.max(1, Math.min(depth, maxChildDepth, maxDepth()));
                }

                applyDepthStyles(li, depth);
            });
        };

        const saveOrder = (tree) => {
            normalizeDepths(tree);
            $wire.saveOrder(buildTreeFromFlat(tree));
        };

        const onPointerMove = (event) => {
            currentX = event.clientX;

            if (! dragBlock.length || ! dragBlock[0]?.isConnected) {
                return;
            }

            setBlockDepths(proposedRootDepth(dragBlock[0]));
        };

        const destroySortable = () => {
            sortable?.destroy();
            sortable = null;
            document.removeEventListener('pointermove', onPointerMove);
        };

        const initSortable = () => {
            const tree = document.getElementById('menu-tree');

            if (! tree) {
                return;
            }

            if (typeof Sortable === 'undefined') {
                setTimeout(initSortable, 50);
                return;
            }

            destroySortable();

            menuItems(tree).forEach((li) => {
                applyDepthStyles(li, Number(li.dataset.depth || 1));
            });

            applyCollapsed(tree);

            document.addEventListener('pointermove', onPointerMove);

            sortable = new Sortable(tree, {
                animation: 150,
                handle: '.cursor-move',
                draggable: '.menu-item',
                filter: '[data-collapse-toggle], [hidden]',
                preventOnFilter: true,
                forceFallback: true,
                fallbackOnBody: true,
                fallbackTolerance: 5,
                swapThreshold: 0.65,
                ghostClass: 'opacity-40',
                onStart: (evt) => {
                    const clientX = evt.originalEvent?.clientX
                        ?? evt.originalEvent?.touches?.[0]?.clientX
                        ?? startX;

                    startX = clientX;
                    currentX = clientX;
                    dragBlock = collectBlock(evt.item);

                    dragBlock.forEach((el) => {
                        el.dataset.originalDepth = el.dataset.depth;
                    });

                    dragBlock.slice(1).forEach((el) => el.remove());
                },
                onEnd: (evt) => {
                    restoreBlockAfter(evt.item);
                    setBlockDepths(proposedRootDepth(evt.item));

                    dragBlock.forEach((el) => {
                        delete el.dataset.originalDepth;
                    });

                    const treeEl = document.getElementById('menu-tree');
                    dragBlock = [];

                    if (treeEl) {
                        saveOrder(treeEl);
                    }
                },
            });
        };

        initSortable();

        document.addEventListener('click', (event) => {
            const toggle = event.target.closest('#menu-tree [data-collapse-toggle]');

            if (! toggle) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const item = toggle.closest('li.menu-item');
            const id = String(item?.dataset.id || '');

            if (! id) {
                return;
            }

            const collapsedIds = loadCollapsedIds();

            if (collapsedIds.has(id)) {
                collapsedIds.delete(id);
            } else {
                collapsedIds.add(id);
            }

            saveCollapsedIds(collapsedIds);

            const tree = document.getElementById('menu-tree');

            if (tree) {
                applyCollapsed(tree);
            }
        });

        $wire.on('menu-tree-updated', () => {
            requestAnimationFrame(() => initSortable());
        });

        Livewire.hook('morph.updated', ({ el }) => {
            if (el?.id === 'menu-tree-wrapper' || el?.querySelector?.('#menu-tree')) {
                requestAnimationFrame(() => initSortable());
            }
        });
    </script>
    @endscript
</x-filament-panels::page>
