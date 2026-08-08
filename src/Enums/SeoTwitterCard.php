<?php

namespace BrainzStudios\FilamentMenu\Enums;

enum SeoTwitterCard: string
{
    case Summary = 'summary';
    case SummaryLargeImage = 'summary_large_image';

    public function label(): string
    {
        return __('filament-menu::menu.fields.seo_twitter_card_options.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $card): array => [$card->value => $card->label()]
        )->all();
    }
}
