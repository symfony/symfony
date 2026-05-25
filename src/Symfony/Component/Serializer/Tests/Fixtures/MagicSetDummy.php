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

class MagicSetDummy
{
    public array $params = [];

    public function __set(string $name, mixed $value): void
    {
        $this->params[$name] = $value;
    }

    public function __get(string $name)
    {
        return $this->params[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return true;
    }
}
