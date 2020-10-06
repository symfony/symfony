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

use Symfony\Component\DependencyInjection\DecorationPriorityAwareInterface;

class DummyDecorator3 extends DummyDecorator1 implements DecorationPriorityAwareInterface, DummyInterface
{
    public static function getDecorationPriority(): int
    {
        return 42;
    }

    public function sayHello(): string
    {
        return sprintf('%s & Decorator3', $this->decorated->sayHello());
    }
}
