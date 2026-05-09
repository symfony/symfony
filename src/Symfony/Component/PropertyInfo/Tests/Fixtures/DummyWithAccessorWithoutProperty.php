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

class DummyWithAccessorWithoutProperty
{
    public function __construct(
        private readonly bool $fromConstructor = false,
    ) {
    }

    public function hasUrl(): bool
    {
        return true;
    }

    public function hasFromConstructor()
    {
        return $this->fromConstructor;
    }

    public function canView(): bool
    {
        return false;
    }

    public function isActive(): bool
    {
        return false;
    }
}
