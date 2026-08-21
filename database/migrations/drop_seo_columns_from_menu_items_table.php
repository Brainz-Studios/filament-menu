<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const COLUMNS = [
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

    public function up(): void
    {
        $columns = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('menu_items', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('menu_items', 'seo_title')) {
                $table->json('seo_title')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'seo_description')) {
                $table->json('seo_description')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'seo_keywords')) {
                $table->json('seo_keywords')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'seo_canonical_url')) {
                $table->json('seo_canonical_url')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'seo_og_title')) {
                $table->json('seo_og_title')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'seo_og_description')) {
                $table->json('seo_og_description')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'seo_image')) {
                $table->unsignedBigInteger('seo_image')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'seo_robots')) {
                $table->string('seo_robots')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'seo_og_type')) {
                $table->string('seo_og_type')->nullable();
            }
            if (! Schema::hasColumn('menu_items', 'seo_twitter_card')) {
                $table->string('seo_twitter_card')->nullable();
            }
        });
    }
};
