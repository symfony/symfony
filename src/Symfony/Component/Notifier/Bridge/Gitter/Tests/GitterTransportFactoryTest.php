<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Gitter\Tests;

use Symfony\Component\Notifier\Bridge\Gitter\GitterTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Christin Gruber <c.gruber@touchdesign.de>
 */
final class GitterTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): GitterTransportFactory
    {
        return new GitterTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'gitter://api.gitter.im?room_id=5539a3ee5etest0d3255bfef',
            'gitter://token@api.gitter.im?room_id=5539a3ee5etest0d3255bfef',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'gitter://token@host?room_id=5539a3ee5etest0d3255bfef'];
        yield [false, 'somethingElse://token@host?room_id=5539a3ee5etest0d3255bfef'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['gitter://api.gitter.im?room_id=5539a3ee5etest0d3255bfef'];
    }

    public static function missingRequiredOptionProvider(): iterable
    {
        yield 'missing option: room_id' => ['gitter://token@host'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host?room_id=5539a3ee5etest0d3255bfef'];
        yield ['somethingElse://token@host'];
    }
}
