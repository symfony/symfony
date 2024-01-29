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

class DummyWithWithVariadicParameterConstructor
{
    private $foo;

    private $bar;

    private $baz;

    public function __construct(string $foo, int $bar = 1, Dummy ...$baz)
    {
        $this->foo = $foo;
        $this->bar = $bar;
        $this->baz = $baz;
    }

    public function getFoo(): string
    {
        return $this->foo;
    }

    public function getBar(): int
    {
        return $this->bar;
    }

    /** @return Dummy[] */
    public function getBaz(): array
    {
        return $this->baz;
    }
}
