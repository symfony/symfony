<?php

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost;

class MagicCloneClass
{
    public ?int $id;
    public bool $cloned;

    public function __clone()
    {
        $this->id = null;
        $this->cloned = true;
    }
}
