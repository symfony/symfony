<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Notifier\Bridge\LineNotify\LineNotifyTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Akira Kurozumi <info@a-zumi.net>
 */
final class LineNotifyTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): LineNotifyTransportFactory
    {
        return new LineNotifyTransportFactory();
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'linenotify://host'];
        yield [false, 'somethingElse://host'];
    }

    public static function createProvider(): iterable
    {
        yield [
            'linenotify://host.test',
            'linenotify://token@host.test',
        ];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['linenotify://host.test'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host'];
    }
}
