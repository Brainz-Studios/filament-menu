<?php

namespace BrainzStudios\FilamentMenu\Database\Factories;

use BrainzStudios\FilamentMenu\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'label' => ['cs' => fake('cs_CZ')->word(), 'en' => fake()->word()],
            'slug' => ['cs' => fake('cs_CZ')->slug(), 'en' => fake()->slug()],
            'type' => fake()->randomElement(['internal', 'external']),
            'link' => ['cs' => '/'.fake()->slug(), 'en' => '/'.fake()->slug()],
            'target' => '_self',
            'cta_type' => null,
            'cta_link' => null,
            'cta_target' => '_self',
            'icon' => null,
            'sort_order' => fake()->numberBetween(0, 100),
            'is_published' => true,
        ];
    }
}
