<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Fixtures;

class StaticConstructorDummy
{
    public $foo;
    public $bar;
    public $quz;

    public static function create($foo)
    {
        $dummy = new self();
        $dummy->quz = $foo;

        return $dummy;
    }

    private function __construct()
    {
    }
}
