<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GatewayApi\Tests;

use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Piergiuseppe Longo <piergiuseppe.longo@gmail.com>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class GatewayApiTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): GatewayApiTransportFactory
    {
        return new GatewayApiTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'gatewayapi://gatewayapi.com?from=Symfony',
            'gatewayapi://token@default?from=Symfony',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'gatewayapi://token@host.test?from=Symfony'];
        yield [false, 'somethingElse://token@default?from=Symfony'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['gatewayapi://host.test?from=Symfony'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: from' => ['gatewayapi://token@host.test'];
    }
}
