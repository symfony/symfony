<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures\LazyGhost;

use Symfony\Component\VarExporter\LazyGhostTrait;

class LazyClass
{
    use LazyGhostTrait {
        createLazyGhost as private;
    }

    public int $public;

    public function __construct(\Closure $initializer)
    {
        self::createLazyGhost($initializer, [], $this);
    }
}
