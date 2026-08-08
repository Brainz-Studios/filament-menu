<?php

namespace BrainzStudios\FilamentMenu\Forms\Components;

use Illuminate\View\Component;

class MenuTree extends Component
{
    public array $treeData = [];

    public function render(): string
    {
        return 'filament-menu::forms.components.menu-tree';
    }

    public function with(): array
    {
        return [
            'items' => $this->treeData,
        ];
    }
}
