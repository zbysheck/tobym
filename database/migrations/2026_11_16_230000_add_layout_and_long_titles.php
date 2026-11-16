<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('layout', 16)->default('tiles')->after('theme');
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->text('title')->change();
        });
    }

    public function down(): void
    {
        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->string('title')->change();
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
