<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\AllMySms\Tests;

use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

final class AllMySmsTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): AllMySmsTransportFactory
    {
        return new AllMySmsTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'allmysms://host.test',
            'allmysms://login:apiKey@host.test',
        ];

        yield [
            'allmysms://host.test?from=TEST',
            'allmysms://login:apiKey@host.test?from=TEST',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'allmysms://login:apiKey@default'];
        yield [false, 'somethingElse://login:apiKey@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://login:apiKey@default'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield ['allmysms://login@default'];
        yield ['allmysms://:apiKey@default'];
    }
}
