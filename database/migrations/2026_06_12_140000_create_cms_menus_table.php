<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Itens como documento coeso (G4): [{label, url|page_id, children: [...]}]
            $table->jsonb('items');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_menus');
    }
};
