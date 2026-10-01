<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items') || Schema::hasColumn('menu_items', 'cta_label')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->json('cta_label')->nullable()->after('cta_link');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'cta_label')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('cta_label');
        });
    }
};
