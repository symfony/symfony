<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Preload;

final class Dummy
{
    public A $a;

    public function doSomething(B $b): ?C
    {
        return null;
    }

    public function noTypes($foo)
    {
    }

    public function builtinTypes(int $foo): ?string
    {
    }
}
