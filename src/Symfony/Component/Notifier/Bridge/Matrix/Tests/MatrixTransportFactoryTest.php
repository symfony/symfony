<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Matrix\Tests;

use Symfony\Component\Notifier\Bridge\Matrix\MatrixTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

final class MatrixTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): MatrixTransportFactory
    {
        return new MatrixTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'matrix://host.test',
            'matrix://host.test/?accessToken=1234',
            'matrix://host.test:8008/?accessToken=1234',
            'matrix://host.test:8008/?accessToken=1234&ssl=true',
            'matrix://host.test/?ssl=true',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing api key' => ['matrix://host.test'];
        yield 'invalid api key' => ['matrix://host.test/?ssl=true'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'matrix://host.test/?accessToken=1234'];
        yield [true, 'matrix://host.test:8008/?accessToken=1234'];
        yield [true, 'matrix://host.test:8008/?accessToken=1234&ssl=true'];
        yield [false, 'somethingElse://apiKey@default?from=TEST'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default?from=FROM'];
    }
}
