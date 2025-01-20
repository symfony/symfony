<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Bluesky\Tests;

use Symfony\Component\Notifier\Bridge\Bluesky\BlueskyTransportFactory;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;

class BlueskyTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): BlueskyTransportFactory
    {
        return new BlueskyTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'bluesky://bsky.social',
            'bluesky://user:pass@bsky.social',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'bluesky://foo:bar@bsky.social'];
        yield [false, 'somethingElse://foo:bar@bsky.social'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing user and password token' => ['bluesky://host'];
        yield 'missing password token' => ['bluesky://user@host'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://foo:bar@default'];
    }
}
