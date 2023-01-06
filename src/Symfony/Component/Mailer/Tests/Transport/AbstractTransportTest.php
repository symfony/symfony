<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\BodyRenderer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\MessageListener;
use Symfony\Component\Mailer\Exception\LogicException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @group time-sensitive
 */
class AbstractTransportTest extends TestCase
{
    public function testThrottling()
    {
        $transport = new NullTransport();
        $transport->setMaxPerSecond(2 / 10);
        $message = new RawMessage('');
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);

        $start = time();
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(5, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(10, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(15, time() - $start, 1);

        $start = time();
        $transport->setMaxPerSecond(-3);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
        $transport->send($message, $envelope);
        $this->assertEqualsWithDelta(0, time() - $start, 1);
    }

    public function testSendingRawMessages()
    {
        $this->expectException(LogicException::class);

        $transport = new NullTransport();
        $transport->send(new RawMessage('Some raw email message'));
    }

    public function testNotRenderedTemplatedEmail()
    {
        $this->expectException(LogicException::class);

        $transport = new NullTransport(new EventDispatcher());
        $transport->send((new TemplatedEmail())->htmlTemplate('Some template'));
    }

    public function testRenderedTemplatedEmail()
    {
        $transport = new NullTransport($dispatcher = new EventDispatcher());
        $dispatcher->addSubscriber(new MessageListener(null, new BodyRenderer(new Environment(new ArrayLoader(['tpl' => 'Some message'])))));

        $sentMessage = $transport->send((new TemplatedEmail())->to('me@example.com')->from('me@example.com')->htmlTemplate('tpl'));
        $this->assertMatchesRegularExpression('/Some message/', $sentMessage->getMessage()->toString());
    }

    public function testRejectMessage()
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(MessageEvent::class, fn (MessageEvent $event) => $event->reject(), 255);
        $dispatcher->addListener(MessageEvent::class, fn () => throw new \RuntimeException('Should never be called.'));

        $transport = new class($dispatcher, $this) extends AbstractTransport {
            public function __construct(EventDispatcherInterface $dispatcher, private TestCase $test)
            {
                parent::__construct($dispatcher);
            }

            protected function doSend(SentMessage $message): void
            {
                $this->test->fail('This should never be called as message is rejected.');
            }

            public function __toString(): string
            {
                return 'fake://';
            }
        };

        $message = new RawMessage('');
        $envelope = new Envelope(new Address('fabien@example.com'), [new Address('helene@example.com')]);
        $this->assertNull($transport->send($message, $envelope));
    }
}
