<?php

namespace BrainzStudios\FilamentMenu\Forms\Components;

use BrainzStudios\FilamentMenu\Enums\SeoOgType;
use BrainzStudios\FilamentMenu\Enums\SeoRobots;
use BrainzStudios\FilamentMenu\Enums\SeoTwitterCard;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

final class SeoSection
{
    /**
     * @return list<mixed>
     */
    private static function localeFields(string $locale): array
    {
        return [
            TextInput::make("seo_title.{$locale}")
                ->label(__('filament-menu::menu.fields.seo_title'))
                ->maxLength(70)
                ->helperText(__('filament-menu::menu.fields.seo_title_help')),
            Textarea::make("seo_description.{$locale}")
                ->label(__('filament-menu::menu.fields.seo_description'))
                ->rows(3)
                ->maxLength(160)
                ->helperText(__('filament-menu::menu.fields.seo_description_help')),
            TextInput::make("seo_keywords.{$locale}")
                ->label(__('filament-menu::menu.fields.seo_keywords'))
                ->helperText(__('filament-menu::menu.fields.seo_keywords_help')),
            TextInput::make("seo_canonical_url.{$locale}")
                ->label(__('filament-menu::menu.fields.seo_canonical_url'))
                ->url()
                ->helperText(__('filament-menu::menu.fields.seo_canonical_url_help')),
            TextInput::make("seo_og_title.{$locale}")
                ->label(__('filament-menu::menu.fields.seo_og_title'))
                ->maxLength(95)
                ->helperText(__('filament-menu::menu.fields.seo_og_title_help')),
            Textarea::make("seo_og_description.{$locale}")
                ->label(__('filament-menu::menu.fields.seo_og_description'))
                ->rows(3)
                ->maxLength(200)
                ->helperText(__('filament-menu::menu.fields.seo_og_description_help')),
        ];
    }

    public static function make(): Section
    {
        /** @var list<string> $locales */
        $locales = config('filament-menu.locales', ['cs', 'en']);
        $tabs = [];

        foreach ($locales as $locale) {
            $tabs[$locale] = self::localeFields($locale);
        }

        return Section::make(__('filament-menu::menu.sections.seo'))
            ->description(__('filament-menu::menu.sections.seo_description'))
            ->schema([
                TranslatableTabs::make($tabs),
                TextInput::make('seo_image')
                    ->label(__('filament-menu::menu.fields.seo_image'))
                    ->numeric()
                    ->helperText(__('filament-menu::menu.fields.seo_image_help')),
                Select::make('seo_robots')
                    ->label(__('filament-menu::menu.fields.seo_robots'))
                    ->options(SeoRobots::options())
                    ->native(false)
                    ->helperText(__('filament-menu::menu.fields.seo_robots_help')),
                Select::make('seo_og_type')
                    ->label(__('filament-menu::menu.fields.seo_og_type'))
                    ->options(SeoOgType::options())
                    ->native(false)
                    ->helperText(__('filament-menu::menu.fields.seo_og_type_help')),
                Select::make('seo_twitter_card')
                    ->label(__('filament-menu::menu.fields.seo_twitter_card'))
                    ->options(SeoTwitterCard::options())
                    ->native(false)
                    ->helperText(__('filament-menu::menu.fields.seo_twitter_card_help')),
            ])
            ->collapsed()
            ->collapsible();
    }
}
