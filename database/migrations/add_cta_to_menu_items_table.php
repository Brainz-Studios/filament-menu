<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        if (! Schema::hasColumn('menu_items', 'cta_type')) {
            Schema::table('menu_items', function (Blueprint $table): void {
                $table->string('cta_type')->nullable()->after('target');
            });
        }

        if (! Schema::hasColumn('menu_items', 'cta_link')) {
            Schema::table('menu_items', function (Blueprint $table): void {
                $table->json('cta_link')->nullable()->after('cta_type');
            });
        }

        if (! Schema::hasColumn('menu_items', 'cta_target')) {
            Schema::table('menu_items', function (Blueprint $table): void {
                $table->string('cta_target')->default('_self')->after('cta_link');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items')) {
            return;
        }

        $columns = array_values(array_filter(
            ['cta_target', 'cta_link', 'cta_type'],
            fn (string $column): bool => Schema::hasColumn('menu_items', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
