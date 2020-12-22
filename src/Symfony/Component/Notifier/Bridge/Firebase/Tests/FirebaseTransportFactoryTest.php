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
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class FirebaseTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return FirebaseTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new FirebaseTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'firebase://host.test',
            'firebase://username:password@host.test',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'firebase://username:password@default'];
        yield [false, 'somethingElse://username:password@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://username:password@default'];
    }
}
