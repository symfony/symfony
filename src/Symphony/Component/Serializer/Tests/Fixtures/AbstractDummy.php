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

use Symphony\Component\Serializer\Annotation\DiscriminatorMap;

/**
 * @DiscriminatorMap(typeProperty="type", mapping={
 *    "first"="Symphony\Component\Serializer\Tests\Fixtures\AbstractDummyFirstChild",
 *    "second"="Symphony\Component\Serializer\Tests\Fixtures\AbstractDummySecondChild"
 * })
 */
abstract class AbstractDummy
{
    public $foo;

    public function __construct($foo = null)
    {
        $this->foo = $foo;
    }
}
