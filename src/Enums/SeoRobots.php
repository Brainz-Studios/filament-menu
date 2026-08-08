<?php

namespace BrainzStudios\FilamentMenu\Enums;

enum SeoRobots: string
{
    case IndexFollow = 'index,follow';
    case IndexNofollow = 'index,nofollow';
    case NoindexFollow = 'noindex,follow';
    case NoindexNofollow = 'noindex,nofollow';

    public function label(): string
    {
        return __('filament-menu::menu.fields.seo_robots_options.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $robots): array => [$robots->value => $robots->label()]
        )->all();
    }
}
