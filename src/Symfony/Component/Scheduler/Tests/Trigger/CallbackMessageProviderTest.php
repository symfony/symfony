<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Trigger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\CallbackMessageProvider;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class CallbackMessageProviderTest extends TestCase
{
    public function testToString()
    {
        $context = new MessageContext('test', 'test', $this->createMock(TriggerInterface::class), $this->createMock(\DateTimeImmutable::class));
        $messageProvider = new CallbackMessageProvider(fn () => []);
        $this->assertEquals([], $messageProvider->getMessages($context));
        $this->assertEquals('', $messageProvider->getId());

        $messageProvider = new CallbackMessageProvider(fn () => [new \stdClass()], '');
        $this->assertEquals([new \stdClass()], $messageProvider->getMessages($context));
        $this->assertSame('', $messageProvider->getId());

        $messageProvider = new CallbackMessageProvider(fn () => yield new \stdClass(), 'foo', 'bar');
        $this->assertInstanceOf(\Generator::class, $messageProvider->getMessages($context));
        $this->assertSame('foo', $messageProvider->getId());
        $this->assertSame('bar', (string) $messageProvider);
    }
}
