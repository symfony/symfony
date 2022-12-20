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

use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class SlackTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return SlackTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new SlackTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'slack://host.test',
            'slack://xoxb-TestToken@host.test',
        ];

        yield 'with path' => [
            'slack://host.test?channel=testChannel',
            'slack://xoxb-TestToken@host.test/?channel=testChannel',
        ];

        yield 'without path' => [
            'slack://host.test?channel=testChannel',
            'slack://xoxb-TestToken@host.test?channel=testChannel',
        ];
    }

    public function testCreateWithDeprecatedDsn()
    {
        $factory = $this->createFactory();

        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Support for Slack webhook DSN has been dropped since 5.2 (maybe you haven\'t updated the DSN when upgrading from 5.1).');

        $factory->create(new Dsn('slack://default/XXXXXXXXX/XXXXXXXXX/XXXXXXXXXXXXXXXXXXXXXXXX'));
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'slack://xoxb-TestToken@host?channel=testChannel'];
        yield [false, 'somethingElse://xoxb-TestToken@host?channel=testChannel'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['slack://host.test?channel=testChannel'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://xoxb-TestToken@host?channel=testChannel'];
    }
}
