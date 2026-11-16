<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('draft_token', 64)->nullable()->unique();
            $table->string('title');
            $table->string('theme', 32);
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $table->text('url')->nullable();
            $table->string('title');
            $table->string('shop')->nullable();
            $table->text('image_url')->nullable();
            $table->string('price', 64)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('wishlists');
    }
};
