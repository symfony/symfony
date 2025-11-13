<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Primotexto\Tests;

use Symfony\Component\Notifier\Bridge\Primotexto\PrimotextoTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

final class PrimotextoTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): PrimotextoTransportFactory
    {
        return new PrimotextoTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'primotexto://host.test',
            'primotexto://apiKey@host.test',
        ];

        yield [
            'primotexto://host.test?from=TEST',
            'primotexto://apiKey@host.test?from=TEST',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'primotexto://apiKey@default'];
        yield [false, 'somethingElse://apiKey@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['primotexto://default'];
    }
}
