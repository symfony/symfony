<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Tests\Fixtures;

class ReflectionExtractorTestFixture
{
    public function __construct($propertyConstruct)
    {
    }

    public function getFoo(): string
    {
        return 'string';
    }

    public function setFoo(string $foo)
    {
    }

    public function bar(?string $bar): string
    {
        return 'string';
    }

    public function isBaz(): bool
    {
        return true;
    }

    public function hasFoz(): bool
    {
        return false;
    }

    public function __get($name)
    {
    }

    public function __set($name, $value)
    {
    }
}
