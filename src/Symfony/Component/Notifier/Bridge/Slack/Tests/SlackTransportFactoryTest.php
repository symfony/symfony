<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;

final class SlackTransportFactoryTest extends TestCase
{
    public function testCreateWithDsn(): void
    {
        $factory = new SlackTransportFactory();

        $host = 'testHost';
        $path = 'testPath';
        $transport = $factory->create(Dsn::fromString(sprintf('slack://%s/%s', $host, $path)));

        $this->assertSame(sprintf('slack://%s/%s', $host, $path), (string) $transport);
    }

    public function testSupportsSlackScheme(): void
    {
        $factory = new SlackTransportFactory();

        $this->assertTrue($factory->supports(Dsn::fromString('slack://host/path')));
        $this->assertFalse($factory->supports(Dsn::fromString('somethingElse://host/path')));
    }

    public function testNonSlackSchemeThrows(): void
    {
        $factory = new SlackTransportFactory();

        $this->expectException(UnsupportedSchemeException::class);

        $factory->create(Dsn::fromString('somethingElse://host/path'));
    }
}
