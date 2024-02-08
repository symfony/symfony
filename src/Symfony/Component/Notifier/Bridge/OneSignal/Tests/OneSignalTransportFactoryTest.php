<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\OneSignal\Tests;

use Symfony\Component\Notifier\Bridge\OneSignal\OneSignalTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Tomas Norkūnas <norkunas.tom@gmail.com>
 */
final class OneSignalTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): OneSignalTransportFactory
    {
        return new OneSignalTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'onesignal://app_id@host.test',
            'onesignal://app_id:api_key@host.test',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'onesignal://token@host'];
        yield [false, 'somethingElse://token@host'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing app_id' => ['onesignal://:api_key@host.test'];
        yield 'missing api_key' => ['onesignal://app_id:@host.test'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host'];
    }
}
