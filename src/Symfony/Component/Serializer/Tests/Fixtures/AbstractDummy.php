<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

/**
 * @DiscriminatorMap(typeProperty="type", mapping={
 *    "first"="Symfony\Component\Serializer\Tests\Fixtures\AbstractDummyFirstChild",
 *    "second"="Symfony\Component\Serializer\Tests\Fixtures\AbstractDummySecondChild"
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
