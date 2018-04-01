<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpUnitCoverageTest;

class BarCov
{
    private $foo;

    public function __construct(FooCov $foo)
    {
        $this->foo = $foo;
    }

    public function barZ()
    {
        $this->foo->fooZ();

        return 'bar';
    }
}
