<?php

namespace BrainzStudios\FilamentMenu\Support;

use Closure;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

final class AutoSlug
{
    public static function make(?string $value): string
    {
        return Str::slug(trim((string) $value));
    }

    public static function shouldUpdate(?string $currentSlug, ?string $previousSource, string $newSource): bool
    {
        if (blank($currentSlug)) {
            return true;
        }

        return $currentSlug === self::make($previousSource);
    }

    public static function fromLocalizedField(string $locale): Closure
    {
        return function (Set $set, Get $get, ?string $state, ?string $old) use ($locale): void {
            $slugPath = "slug.{$locale}";
            $newSource = (string) ($state ?? '');
            $previousSource = (string) ($old ?? '');

            if (! self::shouldUpdate($get($slugPath), $previousSource, $newSource)) {
                return;
            }

            $set($slugPath, self::make($newSource));
        };
    }
}
