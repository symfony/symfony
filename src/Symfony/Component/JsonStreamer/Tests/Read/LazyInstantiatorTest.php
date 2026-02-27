<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Tests\Read;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonStreamer\Read\LazyInstantiator;
use Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy;

class LazyInstantiatorTest extends TestCase
{
    public function testCreateLazyGhostUsingPhp()
    {
        $ghost = (new LazyInstantiator())->instantiate(ClassicDummy::class, static function (ClassicDummy $object): void {
            $object->id = 123;
        });

        $this->assertSame(123, $ghost->id);
    }
}

class DummyForLazyInstantiation
{
}
