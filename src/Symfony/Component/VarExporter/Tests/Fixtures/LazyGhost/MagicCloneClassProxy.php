<?php

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost;

use Symfony\Component\VarExporter\LazyGhostTrait;
use Symfony\Component\VarExporter\LazyObjectInterface;

class MagicCloneClassProxy extends MagicCloneClass  implements LazyObjectInterface
{
    use LazyGhostTrait;
}
