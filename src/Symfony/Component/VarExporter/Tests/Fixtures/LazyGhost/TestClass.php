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

class TestClass extends NoMagicClass
{
    use LazyGhostTrait;

    public int $public = 1;
    protected int $protected = 2;
    protected readonly int $protectedReadonly;
    private int $private = 3;

    public function __construct()
    {
        $this->public = -1;
        $this->protected = -2;
        $this->protectedReadonly ??= 2;
        $this->private = -3;
    }
}
