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

class ChildDummyWithSelfReturningAccessor extends DummyWithSelfReturningAccessor
{
    public function isTripled(): bool
    {
        return false;
    }

    public function tripled(): parent
    {
        return new parent(3);
    }
}
