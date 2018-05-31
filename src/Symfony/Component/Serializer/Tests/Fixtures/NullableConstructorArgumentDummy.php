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

class NullableConstructorArgumentDummy
{
    private $foo;

    public function __construct(?\stdClass $foo)
    {
        $this->foo = $foo;
    }

    public function setFoo($foo)
    {
        $this->foo = 'this setter should not be called when using the constructor argument';
    }

    public function getFoo()
    {
        return $this->foo;
    }
}
