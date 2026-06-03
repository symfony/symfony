<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pushy\Tests;

use Symfony\Component\Notifier\Bridge\Pushy\PushyTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;

final class PushyTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    public function createFactory(): PushyTransportFactory
    {
        return new PushyTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['pushy://api.pushy.me', 'pushy://apiKey@api.pushy.me'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'pushy://apiKey'];
        yield [false, 'somethingElse://apiKey'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey'];
    }
}
