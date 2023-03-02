<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Yunpian\Tests;

use Symfony\Component\Notifier\Bridge\Yunpian\YunpianTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class YunpianTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): YunpianTransportFactory
    {
        return new YunpianTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'yunpian://host.test',
            'yunpian://api_key@host.test',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'yunpian://api_key@default'];
        yield [false, 'somethingElse://api_key@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://api_key@default'];
    }
}
