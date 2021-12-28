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
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class YunpianTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return YunpianTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new YunpianTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'yunpian://host.test',
            'yunpian://api_key@host.test',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'yunpian://api_key@default'];
        yield [false, 'somethingElse://api_key@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://api_key@default'];
    }
}
