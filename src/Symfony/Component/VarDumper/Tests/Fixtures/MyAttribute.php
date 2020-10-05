<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Tests\Fixtures;

#[\Attribute]
final class MyAttribute
{
    public function __construct(
        private string $foo = 'default',
        private ?string $extra = null,
    ) {
    }

    public function getFoo(): string
    {
        return $this->foo;
    }

    public function getExtra(): ?string
    {
        return $this->extra;
    }
}
