<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RocketChat\Tests;

use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransportFactory;
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class RocketChatTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return RocketChatTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new RocketChatTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'rocketchat://host.test?channel=testChannel',
            'rocketchat://accessToken@host.test?channel=testChannel',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'rocketchat://token@host?channel=testChannel'];
        yield [false, 'somethingElse://token@host?channel=testChannel'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing option: token' => ['rocketchat://host.test?channel=testChannel'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host?channel=testChannel'];
    }
}
