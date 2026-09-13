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

class WriteTypeMagicDummy
{
    private string $shadowed = '';
    private string $guarded = '';

    public function __set(string $name, mixed $value): void
    {
    }

    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }

    private function setGuarded(string $guarded): void
    {
        $this->guarded = $guarded;
    }
}
