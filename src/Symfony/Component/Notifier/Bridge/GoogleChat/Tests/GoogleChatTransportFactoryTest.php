<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoogleChat\Tests;

use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

final class GoogleChatTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): GoogleChatTransportFactory
    {
        return new GoogleChatTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'googlechat://chat.googleapis.com/AAAAA_YYYYY',
            'googlechat://abcde-fghij:kl_mnopqrstwxyz%3D@chat.googleapis.com/AAAAA_YYYYY',
        ];

        yield [
            'googlechat://chat.googleapis.com/AAAAA_YYYYY?thread_key=abcdefg',
            'googlechat://abcde-fghij:kl_mnopqrstwxyz%3D@chat.googleapis.com/AAAAA_YYYYY?thread_key=abcdefg',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'googlechat://host/path'];
        yield [false, 'somethingElse://host/path'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing credentials' => ['googlechat://chat.googleapis.com/v1/spaces/AAAAA_YYYYY/messages'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://host/path'];
    }
}
