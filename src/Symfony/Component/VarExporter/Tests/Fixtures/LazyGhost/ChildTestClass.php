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

use Symfony\Component\VarExporter\LazyObjectInterface;

class ChildTestClass extends TestClass implements LazyObjectInterface
{
    public int $public = 4;
    public readonly int $publicReadonly;
    protected int $protected = 5;
    protected readonly int $protectedReadonly;
    private int $private = 6;

    public function __construct()
    {
        if (6 !== $this->private) {
            throw new \LogicException('Bad value for TestClass::$private');
        }

        $this->publicReadonly = 4;
        $this->protectedReadonly = 5;

        parent::__construct();

        if (-2 !== $this->protected) {
            throw new \LogicException('Bad value for TestClass::$protected');
        }

        $this->public = -4;
        $this->protected = -5;
        $this->private = -6;
    }
}
