<?php

namespace App\Console\Commands;

use App\Cms\Plugins\PluginManager;
use Illuminate\Console\Command;

class CmsPluginsSync extends Command
{
    protected $signature = 'cms:plugins:sync';

    protected $description = 'Descobre plugins, atualiza o catálogo e materializa o cache de boot';

    public function handle(PluginManager $manager): int
    {
        $ordered = $manager->sync();

        $this->info($ordered === []
            ? 'Nenhum plugin ativo. Cache materializado vazio.'
            : 'Plugins ativos (ordem de boot): '.implode(', ', $ordered));

        return self::SUCCESS;
    }
}
