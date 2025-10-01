<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures;

class Php74Serializable implements \Serializable
{
    public $foo;

    public function __serialize(): array
    {
        return [$this->foo = new \stdClass()];
    }

    public function __unserialize(array $data): void
    {
        [$this->foo] = $data;
    }

    public function __sleep(): array
    {
        throw new \BadMethodCallException();
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException();
    }

    public function serialize(): string
    {
        throw new \BadMethodCallException();
    }

    public function unserialize($ser)
    {
        throw new \BadMethodCallException();
    }
}
