<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('slug')->nullable()->after('normalized_name');
        });

        DB::table('categories')->orderBy('id')->each(function (object $category): void {
            DB::table('categories')->where('id', $category->id)->update([
                'slug' => Str::slug($category->name).'-'.Str::lower(substr((string) $category->id, -6)),
            ]);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->string('active_slug')->nullable()
                ->storedAs('case when deleted_at is null then slug else null end');
            $table->unique('active_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['active_slug', 'slug']);
        });
    }
};
