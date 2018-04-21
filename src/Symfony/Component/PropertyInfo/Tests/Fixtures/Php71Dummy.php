<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

/**
 * @author Teoh Han Hui <teohhanhui@gmail.com>
 */
class Php71Dummy
{
    public function __construct(string $string, int $intPrivate)
    {
    }

    public function getFoo(): ?array
    {
    }

    public function getBuz(): void
    {
    }

    public function setBar(?int $bar)
    {
    }

    public function addBaz(string $baz)
    {
    }
}

class Php71DummyExtended extends Php71Dummy
{
}

class Php71DummyExtended2 extends Php71Dummy
{
    public function __construct(int $intWithAccessor)
    {
    }

    public function getIntWithAccessor()
    {
    }
}
