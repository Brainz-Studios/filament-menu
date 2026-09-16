<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'link')) {
            return;
        }

        // Already JSON (fresh install from updated create migration, or previously migrated).
        $sample = DB::table('menu_items')->orderBy('id')->value('link');

        if ($sample === null && DB::table('menu_items')->count() === 0) {
            $this->ensureJsonLinkColumn();

            return;
        }

        if (is_string($sample)) {
            $decoded = json_decode($sample, true);

            if (is_array($decoded)) {
                return;
            }
        }

        /** @var list<string> $locales */
        $locales = config('filament-menu.locales', ['cs', 'en']);

        Schema::table('menu_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('menu_items', 'link_localized')) {
                $table->json('link_localized')->nullable();
            }
        });

        DB::table('menu_items')->orderBy('id')->each(function (object $row) use ($locales): void {
            $raw = $row->link;
            $decoded = is_string($raw) ? json_decode($raw, true) : null;

            if (is_array($decoded)) {
                $translations = $decoded;
            } else {
                $translations = [];

                foreach ($locales as $locale) {
                    $translations[$locale] = is_string($raw) ? $raw : '';
                }
            }

            DB::table('menu_items')->where('id', $row->id)->update([
                'link_localized' => json_encode(
                    $translations,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                ),
            ]);
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('link');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->json('link')->nullable();
        });

        DB::table('menu_items')->orderBy('id')->each(function (object $row): void {
            DB::table('menu_items')->where('id', $row->id)->update([
                'link' => $row->link_localized,
            ]);
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('link_localized');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('menu_items') || ! Schema::hasColumn('menu_items', 'link')) {
            return;
        }

        /** @var list<string> $locales */
        $locales = config('filament-menu.locales', ['cs', 'en']);
        $fallbackLocale = $locales[0] ?? 'cs';

        Schema::table('menu_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('menu_items', 'link_plain')) {
                $table->string('link_plain')->default('');
            }
        });

        DB::table('menu_items')->orderBy('id')->each(function (object $row) use ($fallbackLocale): void {
            $raw = $row->link;
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $value = '';

            if (is_array($decoded)) {
                $value = (string) ($decoded[$fallbackLocale] ?? collect($decoded)->filter()->first() ?? '');
            } elseif (is_string($raw)) {
                $value = $raw;
            }

            DB::table('menu_items')->where('id', $row->id)->update([
                'link_plain' => $value,
            ]);
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('link');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->string('link')->default('');
        });

        DB::table('menu_items')->orderBy('id')->each(function (object $row): void {
            DB::table('menu_items')->where('id', $row->id)->update([
                'link' => $row->link_plain,
            ]);
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('link_plain');
        });
    }

    private function ensureJsonLinkColumn(): void
    {
        // No-op when empty table — column type may still be string from older create migration.
        // Convert via temporary column so subsequent inserts store JSON.
        if (Schema::hasColumn('menu_items', 'link_localized')) {
            return;
        }

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->json('link_localized')->nullable();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('link');
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->json('link')->nullable();
        });

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropColumn('link_localized');
        });
    }
};
