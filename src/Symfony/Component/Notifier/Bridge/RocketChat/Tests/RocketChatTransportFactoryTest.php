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
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class RocketChatTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): RocketChatTransportFactory
    {
        return new RocketChatTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'rocketchat://host.test?channel=testChannel',
            'rocketchat://accessToken@host.test?channel=testChannel',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'rocketchat://token@host?channel=testChannel'];
        yield [false, 'somethingElse://token@host?channel=testChannel'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['rocketchat://host.test?channel=testChannel'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host?channel=testChannel'];
    }
}
