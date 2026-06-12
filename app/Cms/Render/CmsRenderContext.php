<?php

namespace App\Cms\Render;

use App\Models\Cms\Page;

/**
 * Estado de render por-request (binding SCOPED, nunca singleton — Octane).
 * Um único modo decide o comportamento de todos os componentes x-cms.
 */
class CmsRenderContext
{
    public const MODE_VIEW = 'view';
    public const MODE_COLLECT = 'collect';

    private string $mode = self::MODE_VIEW;

    private ?Page $page = null;

    /** @var array<int, array> pilha de scopes de valores (instância de bloco, item de repeater) */
    private array $scopes = [];

    public function mode(): string
    {
        return $this->mode;
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
    }

    public function isCollecting(): bool
    {
        return $this->mode === self::MODE_COLLECT;
    }

    public function page(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): void
    {
        $this->page = $page;
    }

    public function pushScope(array $values): void
    {
        $this->scopes[] = $values;
    }

    public function popScope(): void
    {
        array_pop($this->scopes);
    }

    /** Valor de um campo no scope corrente. */
    public function value(string $name, mixed $default = null): mixed
    {
        $scope = end($this->scopes);

        return $scope === false ? $default : data_get($scope, $name, $default);
    }

    public function reset(): void
    {
        $this->mode = self::MODE_VIEW;
        $this->page = null;
        $this->scopes = [];
    }
}
