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

use Symfony\Component\DependencyInjection\Tests\Compiler\A;
use Symfony\Component\DependencyInjection\Tests\Compiler\B;
use Symfony\Component\DependencyInjection\Tests\Compiler\Bar;
use Symfony\Component\DependencyInjection\Tests\Compiler\Foo;

/**
 * To test getter autowiring with PHP >= 7.1.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class GetterOverriding
{
    /** @required */
    public function getFoo(): ?Foo
    {
        // should be called
    }

    /** @required */
    protected function getBar(): Bar
    {
        // should be called
    }

    /** @required */
    public function getNoTypeHint()
    {
        // should not be called
    }

    /** @required */
    public function getUnknown(): NotExist
    {
        // should not be called
    }

    /** @required */
    public function getExplicitlyDefined(): B
    {
        // should be called but not autowired
    }

    /** @required */
    public function getScalar(): string
    {
        // should not be called
    }

    final public function getFinal(): A
    {
        // should not be called
    }

    public function &getReference(): A
    {
        // should not be called
    }
}
