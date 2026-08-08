<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->json('label');
            $table->json('slug')->nullable();
            $table->string('type')->default('internal');
            $table->string('link')->default('');
            $table->string('target')->default('_self');
            $table->string('icon')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->json('seo_title')->nullable();
            $table->json('seo_description')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->json('seo_canonical_url')->nullable();
            $table->json('seo_og_title')->nullable();
            $table->json('seo_og_description')->nullable();
            $table->unsignedBigInteger('seo_image')->nullable();
            $table->string('seo_robots')->nullable();
            $table->string('seo_og_type')->nullable();
            $table->string('seo_twitter_card')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
