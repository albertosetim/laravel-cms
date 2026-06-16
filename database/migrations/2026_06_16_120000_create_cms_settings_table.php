<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_settings', function (Blueprint $table) {
            $table->id();

            // Owner polimórfico estilo media. Sentinela 'general'/0 para o bucket geral;
            // FQCN do model/0 para settings de tipo. settable_id NÃO é nulo (default 0) de
            // propósito: no Postgres NULLs são distintos num índice unique, o que partiria a
            // unicidade do bucket geral.
            $table->string('settable_type');
            $table->unsignedBigInteger('settable_id')->default(0);

            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['settable_type', 'settable_id', 'key']);
            $table->index(['settable_type', 'settable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_settings');
    }
};
