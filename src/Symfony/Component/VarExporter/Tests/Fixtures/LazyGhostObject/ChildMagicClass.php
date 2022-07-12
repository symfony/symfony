<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyGhostObject;

use Symfony\Component\VarExporter\LazyGhostObjectInterface;
use Symfony\Component\VarExporter\LazyGhostObjectTrait;

class ChildMagicClass extends MagicClass implements LazyGhostObjectInterface
{
    use LazyGhostObjectTrait;

    private int $data = 123;
}
