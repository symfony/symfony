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
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
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
            'slack://host.test/id',
            'slack://host.test/id',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'slack://host.test/id'];
        yield [false, 'somethingElse://host.test/id'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing path' => ['slack://host.test'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://host.test/id'];
        yield ['somethingElse://host.test']; // missing "id"
    }
}
