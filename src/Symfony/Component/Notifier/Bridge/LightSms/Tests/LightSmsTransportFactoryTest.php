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
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

final class LightSmsTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): LightSmsTransportFactory
    {
        return new LightSmsTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'lightsms://host.test?from=0611223344',
            'lightsms://login:token@host.test?from=0611223344',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'lightsms://login:token@default?from=37061234567'];
        yield [false, 'somethingElse://login:token@default?from=37061234567'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://login:token@default?from=37061234567'];
        yield ['somethingElse://login:token@default']; // missing "from" option
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['lightsms://login@default?from=37061234567'];
        yield ['lightsms://:token@default?from=37061234567'];
    }
}
