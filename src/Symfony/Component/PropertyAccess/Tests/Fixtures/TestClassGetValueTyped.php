<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class TestClassGetValueTyped
{
    protected $value;

    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    public function getValue(): TestClassGetValueTyped
    {
        return $this->value;
    }

    public function setValue(TestClassGetValueTyped $value)
    {
        $this->value = $value;
    }
}
