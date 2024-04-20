<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase\Tests;

use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FirebaseTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): FirebaseTransportFactory
    {
        return new FirebaseTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'firebase://host.test',
            'firebase://username:password@host.test',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'firebase://username:password@default'];
        yield [false, 'somethingElse://username:password@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default'];
    }
}
