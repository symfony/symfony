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

class Php81Dummy
{
    public function __construct(public readonly string $foo)
    {
    }

    public function getNothing(): never
    {
        throw new \Exception('Oops');
    }

    public function getCollection(): \Traversable&\Countable
    {
    }
}
