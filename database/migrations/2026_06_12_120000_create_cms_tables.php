<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('translation_group_id');
            $table->string('locale', 10);
            $table->string('slug');
            $table->string('name');
            $table->string('template')->default('default');
            $table->foreignId('parent_id')->nullable()->constrained('cms_pages')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('show_in_menu')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'position']);
            $table->unique(['parent_id', 'locale', 'slug']);
            $table->index('status');
            $table->index('translation_group_id');
        });

        Schema::create('cms_page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->jsonb('data');
            $table->boolean('is_draft')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['page_id', 'is_draft']);
            $table->index(['page_id', 'created_at']);
        });

        Schema::table('cms_pages', function (Blueprint $table) {
            $table->foreign('published_revision_id')
                ->references('id')->on('cms_page_revisions')
                ->nullOnDelete();
        });

        Schema::create('cms_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->jsonb('blueprint');
            $table->jsonb('options')->nullable();
            $table->boolean('promoted')->default(false);
            $table->timestamps();
        });

        Schema::create('cms_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_id')->constrained('cms_types')->cascadeOnDelete();
            $table->uuid('translation_group_id')->nullable();
            $table->string('locale', 10)->nullable();
            $table->string('slug')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->jsonb('data');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type_id', 'status']);
            $table->unique(['type_id', 'slug', 'locale']);
        });

        // O GIN sobre data é o que torna campos de tipos de admin filtráveis
        // sem DDL por tipo e sem EAV (G2/G4).
        DB::statement('CREATE INDEX cms_entries_data_gin ON cms_entries USING GIN (data jsonb_path_ops)');

        Schema::create('cms_plugins', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('version')->default('1.0.0');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_plugins');
        Schema::dropIfExists('cms_entries');
        Schema::dropIfExists('cms_types');
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropForeign(['published_revision_id']);
        });
        Schema::dropIfExists('cms_page_revisions');
        Schema::dropIfExists('cms_pages');
    }
};
