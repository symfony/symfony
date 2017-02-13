<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Fixtures;

use Symfony\Component\DependencyInjection\Tests\Compiler\Foo;

abstract class AbstractGetterOverriding
{
    abstract public function abstractGetFoo(): Foo;
    abstract public function abstractDoFoo($arg = null): Foo;
}
