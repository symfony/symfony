<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\DirectAccess;

class MagicSetTarget
{
    public int $count;
    public array $calls = [];

    public function __construct()
    {
        unset($this->count);
    }

    public function __set(string $property, mixed $value): void
    {
        $this->calls[] = $property;
        $this->$property = $value;
    }
}
