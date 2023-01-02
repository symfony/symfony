<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\PagerDuty\Tests;

use Symfony\Component\Notifier\Bridge\PagerDuty\PagerDutyOptions;
use Symfony\Component\Notifier\Bridge\PagerDuty\PagerDutyTransport;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Test\TransportTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PagerDutyTransportTest extends TransportTestCase
{
    public function createTransport(HttpClientInterface $client = null): PagerDutyTransport
    {
        return (new PagerDutyTransport('testToken', $client ?? $this->createMock(HttpClientInterface::class)))->setHost('test.pagerduty.com');
    }

    public function toStringProvider(): iterable
    {
        yield ['pagerduty://test.pagerduty.com', $this->createTransport()];
    }

    public function supportedMessagesProvider(): iterable
    {
        yield [new PushMessage('Source', 'Summary')];
        yield [new PushMessage('Source', 'Summary', new PagerDutyOptions('e93facc04764012d7bfb002500d5d1a6', 'trigger', 'info'))];
        yield [new PushMessage('Source', 'Summary', new PagerDutyOptions('e93facc04764012d7bfb002500d5d1a6', 'acknowledge', 'info', ['dedup_key' => 'srv01/test']))];
    }

    public function unsupportedMessagesProvider(): iterable
    {
        yield [new SmsMessage('0611223344', 'Hello!')];
        yield [new ChatMessage('Hello!')];
        yield [$this->createMock(MessageInterface::class)];
    }
}
