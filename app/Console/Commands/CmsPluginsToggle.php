<?php

namespace App\Console\Commands;

use App\Cms\Plugins\PluginManager;
use Illuminate\Console\Command;

abstract class CmsPluginsToggle extends Command
{
    abstract protected function enable(): bool;

    public function handle(PluginManager $manager): int
    {
        try {
            $manager->setEnabled($this->argument('slug'), $this->enable());
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            "Plugin '{$this->argument('slug')}' ".($this->enable() ? 'ativado' : 'desativado')
            .'. Corre cms:build para atualizar a paleta de blocos.',
        );

        return self::SUCCESS;
    }
}
