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
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class SlackTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SlackTransportFactory
    {
        return new SlackTransportFactory();
    }

    public static function createProvider(): iterable
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

    public static function supportsProvider(): iterable
    {
        yield [true, 'slack://xoxb-TestToken@host?channel=testChannel'];
        yield [false, 'somethingElse://xoxb-TestToken@host?channel=testChannel'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['slack://host.test?channel=testChannel'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://xoxb-TestToken@host?channel=testChannel'];
    }
}
