<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sipgate\Tests;

use Symfony\Component\Notifier\Bridge\Sipgate\SipgateTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

class SipgateTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SipgateTransportFactory
    {
        return new SipgateTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'sipgate://host.test?senderId=s1',
            'sipgate://tokenId:token@host.test?senderId=s1',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'sipgate://tokenId:token@host.test?senderId=s1'];
        yield [false, 'somethingElse://tokenId:token@host.test?senderId=s1'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://tokenId:token@host.test?senderId=s1'];
        yield ['somethingElse://tokenId:token@host.test']; // missing senderId
    }
}
