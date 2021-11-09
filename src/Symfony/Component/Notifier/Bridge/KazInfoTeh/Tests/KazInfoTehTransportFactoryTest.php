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

use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;
use Symfony\Component\Notifier\Bridge\KazInfoTeh\KazInfoTehTransportFactory;

/**
 * @author Egor Taranov <dev@taranovegor.com>
 */
final class KazInfoTehTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return KazInfoTehTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new KazInfoTehTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'kazinfoteh://kazinfoteh.org:9507?sender=symfony',
            'kazinfoteh://username:password@default?sender=symfony',
        ];

        yield [
            'kazinfoteh://host.test?sender=Symfony',
            'kazinfoteh://username:password@host.test?sender=Symfony',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'kazinfoteh://username:password@default?from=Symfony'];
        yield [false, 'somethingElse://username:password@default?from=Symfony'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: sender' => ['kazinfoteh://username:password@default'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing username' => ['kazinfoteh://default?sender=0611223344'];
        yield 'missing password' => ['kazinfoteh://username@default?sender=0611223344'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default?sender=acme'];
        yield ['somethingElse://username:password@default'];
        yield ['somethingElse://default'];
    }
}
