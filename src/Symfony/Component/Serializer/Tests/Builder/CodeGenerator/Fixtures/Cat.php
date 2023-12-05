<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Builder\CodeGenerator\Fixtures;

class Cat
{
    public function __construct(private string $name, private int $age)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAge(): int
    {
        return $this->age;
    }
}
