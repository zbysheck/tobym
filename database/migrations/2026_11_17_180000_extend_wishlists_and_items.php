<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            // Jedna przeglądarka może mieć kilka szkiców list, więc token przestaje być unikalny.
            $table->dropUnique(['draft_token']);
            $table->index('draft_token');
            $table->date('event_date')->nullable()->after('layout');
            $table->string('accent', 7)->nullable()->after('event_date');
            $table->string('pin_hash')->nullable()->after('accent');
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->text('note')->nullable()->after('price');
            $table->boolean('priority')->default(false)->after('note');
            $table->string('image_path')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropColumn(['note', 'priority', 'image_path']);
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn(['event_date', 'accent', 'pin_hash']);
            $table->dropIndex(['draft_token']);
            $table->unique('draft_token');
        });
    }
};
