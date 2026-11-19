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

class Php84DummyWithMagicSet
{
    public private(set) mixed $publicPrivate;
    public protected(set) mixed $publicProtected;
    protected private(set) mixed $protectedPrivate;
    public bool $virtualNoSetHook { get => true; }

    public function __set(string $name, mixed $value): void
    {
    }
}
