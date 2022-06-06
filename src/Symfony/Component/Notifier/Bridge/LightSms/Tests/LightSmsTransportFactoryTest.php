<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LightSms\Tests;

use Symfony\Component\Notifier\Bridge\LightSms\LightSmsTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class LightSmsTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return LightSmsTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new LightSmsTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'lightsms://host.test?from=0611223344',
            'lightsms://login:token@host.test?from=0611223344',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'lightsms://login:token@default?from=37061234567'];
        yield [false, 'somethingElse://login:token@default?from=37061234567'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://login:token@default?from=37061234567'];
        yield ['somethingElse://login:token@default']; // missing "from" option
    }
}
