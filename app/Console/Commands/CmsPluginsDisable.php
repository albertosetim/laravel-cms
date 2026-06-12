<?php

namespace App\Console\Commands;

class CmsPluginsDisable extends CmsPluginsToggle
{
    protected $signature = 'cms:plugins:disable {slug}';

    protected $description = 'Desativa um plugin e rematerializa o cache de boot';

    protected function enable(): bool
    {
        return false;
    }
}
