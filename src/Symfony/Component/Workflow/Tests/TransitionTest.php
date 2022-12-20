<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Workflow\Transition;

class TransitionTest extends TestCase
{
    public function testConstructor()
    {
        $transition = new Transition('name', 'a', 'b');

        self::assertSame('name', $transition->getName());
        self::assertSame(['a'], $transition->getFroms());
        self::assertSame(['b'], $transition->getTos());
    }
}
