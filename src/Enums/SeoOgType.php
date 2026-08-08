<?php

namespace BrainzStudios\FilamentMenu\Enums;

enum SeoOgType: string
{
    case Website = 'website';
    case Article = 'article';
    case Profile = 'profile';
    case Book = 'book';

    public function label(): string
    {
        return __('filament-menu::menu.fields.seo_og_type_options.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $type): array => [$type->value => $type->label()]
        )->all();
    }
}
