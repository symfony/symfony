<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\KazInfoTeh\Tests;

use Symfony\Component\Notifier\Bridge\KazInfoTeh\KazInfoTehTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Egor Taranov <dev@taranovegor.com>
 */
final class KazInfoTehTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): KazInfoTehTransportFactory
    {
        return new KazInfoTehTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'kaz-info-teh://kazinfoteh.org:9507?sender=symfony',
            'kaz-info-teh://username:password@default?sender=symfony',
        ];

        yield [
            'kaz-info-teh://host.test?sender=Symfony',
            'kaz-info-teh://username:password@host.test?sender=Symfony',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'kaz-info-teh://username:password@default?sender=Symfony'];
        yield [false, 'somethingElse://username:password@default?sender=Symfony'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: sender' => ['kaz-info-teh://username:password@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing username' => ['kaz-info-teh://default?sender=0611223344'];
        yield 'missing password' => ['kaz-info-teh://username@default?sender=0611223344'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default?sender=acme'];
        yield ['somethingElse://username:password@default'];
        yield ['somethingElse://default'];
    }
}
