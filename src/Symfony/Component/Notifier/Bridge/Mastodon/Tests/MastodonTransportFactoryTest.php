<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mastodon\Tests;

use Symfony\Component\Notifier\Bridge\Mastodon\MastodonTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;

/**
 * @author Quentin Dequippe <quentin@dequippe.tech>
 */
class MastodonTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): MastodonTransportFactory
    {
        return new MastodonTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'mastodon://host.test',
            'mastodon://accessToken@host.test',
        ];

        yield [
            'mastodon://example.com',
            'mastodon://accessToken@example.com',
        ];

        yield [
            'mastodon://example.com',
            'mastodon://accessToken@example.com',
        ];

        yield [
            'mastodon://example.com',
            'mastodon://accessToken@example.com',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'mastodon://token@host'];
        yield [false, 'somethingElse://token@host'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing token' => ['mastodon://host.test'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://token@host'];
    }
}
