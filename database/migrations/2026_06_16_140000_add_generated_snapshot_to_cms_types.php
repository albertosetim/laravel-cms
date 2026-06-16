<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda o snapshot do blueprint/relações no momento da última geração. É a
 * referência contra a qual se calcula o diff quando o tipo é editado, para
 * emitir uma migration de ALTER (ver TypeGenerator::regenerate + SchemaDiffer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_types', function (Blueprint $table) {
            $table->jsonb('generated_blueprint')->nullable()->after('generated');
            $table->jsonb('generated_relation_defs')->nullable()->after('generated_blueprint');
        });

        // Tipos já gerados antes desta feature: o estado atual == o estado gerado
        // (não havia forma de editar). Backfill para que o 1.º diff seja correto.
        DB::table('cms_types')->where('generated', true)->update([
            'generated_blueprint' => DB::raw('blueprint'),
            'generated_relation_defs' => DB::raw('relation_defs'),
        ]);
    }

    public function down(): void
    {
        Schema::table('cms_types', function (Blueprint $table) {
            $table->dropColumn(['generated_blueprint', 'generated_relation_defs']);
        });
    }
};
