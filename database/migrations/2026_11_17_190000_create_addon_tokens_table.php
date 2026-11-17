<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('draft_hash', 64)->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_tokens');
    }
};
