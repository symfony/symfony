<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class Bar
{
    private $foo;

    public function __construct(Foo $foo)
    {
        $this->foo = $foo;
    }

    public function barZ()
    {
        $this->foo->fooZ();

        return 'bar';
    }
}
