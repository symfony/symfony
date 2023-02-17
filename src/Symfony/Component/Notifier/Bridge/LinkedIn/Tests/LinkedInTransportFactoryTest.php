<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Tests;

use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

final class LinkedInTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return LinkedInTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new LinkedInTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'linkedin://host.test',
            'linkedin://accessToken:UserId@host.test',
        ];
    }

    public static function supportsProvider(): iterable
    {
        yield [true, 'linkedin://host'];
        yield [false, 'somethingElse://host'];
    }

    public static function incompleteDsnProvider(): iterable
    {
        yield 'missing account or user_id' => ['linkedin://AccessTokenOrUserId@default'];
    }

    public static function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://accessToken:UserId@default'];
    }
}
