<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SimpleTextin\Tests;

use Symfony\Component\Notifier\Bridge\SimpleTextin\SimpleTextinTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class SimpleTextinTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): SimpleTextinTransportFactory
    {
        return new SimpleTextinTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield ['simpletextin://host.test', 'simpletextin://ApiKey@host.test'];
        yield ['simpletextin://host.test?from=15556667777', 'simpletextin://ApiKey@host.test?from=15556667777'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing API key' => ['simpletextin://@default'];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'simpletextin://apiKey@default'];
        yield [false, 'somethingElse://apiKey@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://apiKey@default'];
    }
}
