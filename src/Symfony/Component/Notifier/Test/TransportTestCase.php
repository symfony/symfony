<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Test;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A test case to ease testing a Notifier transport.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
abstract class TransportTestCase extends TestCase
{
    protected const CUSTOM_HOST = 'host.test';
    protected const CUSTOM_PORT = 42;

    abstract public function createTransport(?HttpClientInterface $client = null): TransportInterface;

    /**
     * @return iterable<array{0: string, 1: TransportInterface}>
     */
    abstract public function toStringProvider(): iterable;

    /**
     * @return iterable<array{0: MessageInterface, 1: TransportInterface}>
     */
    abstract public function supportedMessagesProvider(): iterable;

    /**
     * @return iterable<array{0: MessageInterface, 1: TransportInterface}>
     */
    abstract public function unsupportedMessagesProvider(): iterable;

    /**
     * @dataProvider toStringProvider
     */
    public function testToString(string $expected, TransportInterface $transport)
    {
        $this->assertSame($expected, (string) $transport);
    }

    /**
     * @dataProvider supportedMessagesProvider
     */
    public function testSupportedMessages(MessageInterface $message, ?TransportInterface $transport = null)
    {
        if (null === $transport) {
            $transport = $this->createTransport();
        }

        $this->assertTrue($transport->supports($message));
    }

    /**
     * @dataProvider unsupportedMessagesProvider
     */
    public function testUnsupportedMessages(MessageInterface $message, ?TransportInterface $transport = null)
    {
        if (null === $transport) {
            $transport = $this->createTransport();
        }

        $this->assertFalse($transport->supports($message));
    }

    /**
     * @dataProvider unsupportedMessagesProvider
     */
    public function testUnsupportedMessagesTrowUnsupportedMessageTypeExceptionWhenSend(MessageInterface $message, ?TransportInterface $transport = null)
    {
        if (null === $transport) {
            $transport = $this->createTransport();
        }

        $this->expectException(UnsupportedMessageTypeException::class);

        $transport->send($message);
    }

    public function testCanSetCustomHost()
    {
        $transport = $this->createTransport();

        $transport->setHost($customHost = self::CUSTOM_HOST);

        $this->assertStringContainsString(sprintf('://%s', $customHost), (string) $transport);
    }

    public function testCanSetCustomPort()
    {
        $transport = $this->createTransport();

        $transport->setPort($customPort = self::CUSTOM_PORT);

        /*
         * @see https://regex101.com/r/0xQKuY/2
         */
        $this->assertMatchesRegularExpression(sprintf('/^.*\/\/.*\:%s.*$/', $customPort), (string) $transport);
    }

    public function testCanSetCustomHostAndPort()
    {
        $transport = $this->createTransport();

        $transport->setHost($customHost = self::CUSTOM_HOST);
        $transport->setPort($customPort = self::CUSTOM_PORT);

        $this->assertStringContainsString(sprintf('://%s:%s', $customHost, $customPort), (string) $transport);
    }
}
