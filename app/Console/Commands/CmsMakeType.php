<?php

namespace App\Console\Commands;

use App\Cms\Generator\TypeGenerator;
use App\Models\Cms\ContentType;
use Illuminate\Console\Command;

/**
 * Gera Model + Migration + FilamentResource a partir de um ContentType
 * (designer). Caminho canónico (dev-time). O mesmo gerador é usado pelo botão
 * "Gerar código" no admin.
 */
class CmsMakeType extends Command
{
    protected $signature = 'cms:make:type {slug : Slug do tipo em cms_types} {--migrate : Corre migrate no fim}';

    protected $description = 'Gera Model + Migration + Resource a partir de um tipo (designer)';

    public function handle(TypeGenerator $generator): int
    {
        $type = ContentType::query()->where('slug', $this->argument('slug'))->first();

        if ($type === null) {
            $this->error("Tipo '{$this->argument('slug')}' não existe.");

            return self::FAILURE;
        }

        try {
            $written = $generator->generate($type);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Gerado:');
        foreach ($written as $file) {
            $this->line('  '.$file);
        }

        if ($this->option('migrate')) {
            $this->call('migrate', ['--force' => true]);
        } else {
            $this->warn('Revê, ajusta, commita e corre `php artisan migrate`.');
        }

        return self::SUCCESS;
    }
}
