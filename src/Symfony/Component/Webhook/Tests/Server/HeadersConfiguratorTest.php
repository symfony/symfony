<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Tests\Server;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\HttpOptions;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Server\HeadersConfigurator;
use Symfony\Component\Webhook\Server\SignatureFormat;

class HeadersConfiguratorTest extends TestCase
{
    public function testWithoutClockTheTimestampIsTheCurrentTime()
    {
        $options = new HttpOptions();

        (new HeadersConfigurator())->configure(new RemoteEvent('event-name', 'event-id', []), 's3cr3t', $options);

        $headers = $options->toArray()['headers'];
        $this->assertSame('event-name', $headers['Webhook-Event']);
        $this->assertSame('event-id', $headers['Webhook-Id']);
        $this->assertLessThanOrEqual(time(), (int) $headers['Webhook-Timestamp']);
    }

    public function testTheTimestampComesFromTheClock()
    {
        $options = new HttpOptions();

        (new HeadersConfigurator(clock: new MockClock('@1674087231')))
            ->configure(new RemoteEvent('event-name', 'event-id', []), 's3cr3t', $options);

        $this->assertSame('1674087231', $options->toArray()['headers']['Webhook-Timestamp']);
    }

    public function testTheEventHeaderIsNotSentInStandardFormat()
    {
        $options = new HttpOptions();

        (new HeadersConfigurator(format: SignatureFormat::Standard))
            ->configure(new RemoteEvent('event-name', 'event-id', []), 's3cr3t', $options);

        $headers = $options->toArray()['headers'];
        $this->assertArrayNotHasKey('Webhook-Event', $headers);
        $this->assertSame('event-id', $headers['Webhook-Id']);
    }

    public function testTheEventHeaderIsStillSentInTransitionalFormat()
    {
        $options = new HttpOptions();

        (new HeadersConfigurator(format: SignatureFormat::Transitional))
            ->configure(new RemoteEvent('event-name', 'event-id', []), 's3cr3t', $options);

        $this->assertSame('event-name', $options->toArray()['headers']['Webhook-Event']);
    }
}
