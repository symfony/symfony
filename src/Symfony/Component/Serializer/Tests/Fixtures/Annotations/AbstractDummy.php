<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures\Annotations;

use Symfony\Component\Serializer\Annotation\DiscriminatorMap;

/**
 * @DiscriminatorMap(typeProperty="type", mapping={
 *    "first"="Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummyFirstChild",
 *    "second"="Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummySecondChild",
 *    "third"="Symfony\Component\Serializer\Tests\Fixtures\Annotations\AbstractDummyThirdChild",
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
