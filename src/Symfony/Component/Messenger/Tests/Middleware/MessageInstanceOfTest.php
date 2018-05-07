<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Middleware\MessageInstanceOf;

final class MessageInstanceOfTest extends TestCase
{
    public function testClassesThatImplementTheInterfaceReturned()
    {
        $messageClasses = iterator_to_array(new MessageInstanceOf(DummyMessageInterface::class));
        $expected = array(
            DummyMessage1::class,
            DummyMessage3::class,
        );

        $this->assertSame($expected, $messageClasses);
    }

    public function testClassesExtendingAbstractClassReturned()
    {
        $messageClasses = iterator_to_array(new MessageInstanceOf(AbstractDummyMessage::class));
        $expected = array(
            DummyMessage2::class,
            DummyMessage3::class,
        );

        $this->assertSame($expected, $messageClasses);
    }

    public function testClassesExtendingInstantiableClassReturnedAndInstantiableClass()
    {
        $messageClasses = iterator_to_array(new MessageInstanceOf(DummyMessage4::class));
        $expected = array(
            DummyMessage4::class,
            DummyMessage5::class,
        );

        $this->assertSame($expected, $messageClasses);
    }
}

interface DummyMessageInterface
{
}

abstract class AbstractDummyMessage
{
}

class DummyMessage1 implements DummyMessageInterface
{
}

class DummyMessage2 extends AbstractDummyMessage
{
}

class DummyMessage3 extends AbstractDummyMessage implements DummyMessageInterface
{
}

class DummyMessage4
{
}

class DummyMessage5 extends DummyMessage4
{
}
