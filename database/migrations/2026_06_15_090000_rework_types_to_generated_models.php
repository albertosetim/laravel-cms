<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: o sistema genérico de entries (jsonb) sai. cms_types passa a ser o
 * DESIGNER de models — define campos e relações que um gerador transforma em
 * Model + Migration + Resource reais. Decisão do utilizador: a geração corre
 * a partir do admin em qualquer ambiente (abandona-se a regra G1 do blueprint).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cms_entries');

        Schema::table('cms_types', function (Blueprint $table) {
            // Nome propositadamente != 'relations': esse colide com a
            // propriedade interna $relations do Eloquent (loaded relations).
            $table->jsonb('relation_defs')->nullable()->after('blueprint');
            $table->boolean('generated')->default(false)->after('relation_defs');
        });
    }

    public function down(): void
    {
        Schema::table('cms_types', function (Blueprint $table) {
            $table->dropColumn(['relation_defs', 'generated']);
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
        });
    }
};
