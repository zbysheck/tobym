<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->string('slug', 16)->nullable()->unique()->after('id');
        });

        foreach (DB::table('wishlists')->whereNull('slug')->pluck('id') as $id) {
            DB::table('wishlists')->where('id', $id)->update(['slug' => strtolower(Str::random(10))]);
        }
    }

    public function down(): void
    {
        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
