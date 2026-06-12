<?php

namespace App\Console\Commands;

class CmsPluginsEnable extends CmsPluginsToggle
{
    protected $signature = 'cms:plugins:enable {slug}';

    protected $description = 'Ativa um plugin e rematerializa o cache de boot';

    protected function enable(): bool
    {
        return true;
    }
}
