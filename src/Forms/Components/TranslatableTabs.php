<?php

namespace BrainzStudios\FilamentMenu\Forms\Components;

use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

final class TranslatableTabs
{
    /**
     * @param  array<string, list<mixed>>  $localeSchemas
     */
    public static function make(array $localeSchemas, ?string $name = null): Tabs
    {
        return Tabs::make($name)
            ->tabs(
                collect($localeSchemas)
                    ->map(
                        fn (array $schema, string $locale): Tab => Tab::make(strtoupper($locale))
                            ->key($locale)
                            ->schema($schema)
                    )
                    ->values()
                    ->all()
            )
            ->columnSpanFull();
    }
}
