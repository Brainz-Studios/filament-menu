<?php

namespace BrainzStudios\FilamentMenu\Models\Concerns;

use BrainzStudios\FilamentMenu\Enums\SeoOgType;
use BrainzStudios\FilamentMenu\Enums\SeoRobots;
use BrainzStudios\FilamentMenu\Enums\SeoTwitterCard;

trait HasSeoAttributes
{
    /**
     * @var list<string>
     */
    public const SEO_TRANSLATABLE = [
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_canonical_url',
        'seo_og_title',
        'seo_og_description',
    ];

    /**
     * @var list<string>
     */
    public const SEO_FILLABLE = [
        'seo_title',
        'seo_description',
        'seo_keywords',
        'seo_canonical_url',
        'seo_og_title',
        'seo_og_description',
        'seo_image',
        'seo_robots',
        'seo_og_type',
        'seo_twitter_card',
    ];

    public function initializeHasSeoAttributes(): void
    {
        $this->mergeFillable(self::SEO_FILLABLE);

        $this->mergeCasts([
            'seo_robots' => SeoRobots::class,
            'seo_og_type' => SeoOgType::class,
            'seo_twitter_card' => SeoTwitterCard::class,
        ]);

        $this->translatable = array_values(array_unique([
            ...($this->translatable ?? []),
            ...self::SEO_TRANSLATABLE,
        ]));
    }
}
