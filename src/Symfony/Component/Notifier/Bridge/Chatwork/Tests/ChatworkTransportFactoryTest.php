<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Chatwork\Tests;

use Symfony\Component\Notifier\Bridge\Chatwork\ChatworkTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

class ChatworkTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): TransportFactoryInterface
    {
        return new ChatworkTransportFactory();
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'chatwork://host?room_id=testRoomId'];
        yield [false, 'somethingElse://host?room_id=testRoomId'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'chatwork://host.test?room_id=testRoomId',
            'chatwork://token@host.test?room_id=testRoomId',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['chatwork://host.test?room_id=testRoomId'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: room_id' => ['chatwork://token@host'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host?room_id=testRoomId'];
        yield ['somethingElse://token@host']; // missing "room_id" option
    }
}
