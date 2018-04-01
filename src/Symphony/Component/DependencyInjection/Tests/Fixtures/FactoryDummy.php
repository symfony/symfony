<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Fixtures;

class FactoryDummy extends FactoryParent
{
    public static function createFactory(): FactoryDummy
    {
    }

    public function create(): \stdClass
    {
    }

    public function createBuiltin(): int
    {
    }

    public static function createSelf(): self
    {
    }

    public static function createParent(): parent
    {
    }
}

class FactoryParent
{
}

function factoryFunction(): FactoryDummy
{
}
