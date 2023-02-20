<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zendesk\Tests;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Notifier\Bridge\Zendesk\ZendeskOptions;
use Symfony\Component\Notifier\Bridge\Zendesk\ZendeskTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ZendeskTransportTest extends TransportTestCase
{
    public static function createTransport(HttpClientInterface $client = null): ZendeskTransport
    {
        return (new ZendeskTransport('testEmail', 'testToken', $client ?? new MockHttpClient()))->setHost('test.zendesk.com');
    }

    public static function toStringProvider(): iterable
    {
        yield ['zendesk://test.zendesk.com', self::createTransport()];
    }

    public static function supportedMessagesProvider(): iterable
    {
        yield [new ChatMessage('Hello!')];
        yield [new ChatMessage('Hello!', new ZendeskOptions('urgent'))];
    }

    public static function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
    }
}
