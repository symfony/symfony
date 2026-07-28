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
use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Test\AbstractTransportFactoryTestCase;
use Symfony\Component\Notifier\Test\IncompleteDsnTestTrait;
use Symfony\Component\Notifier\Transport\Dsn;

final class LinkedInTransportFactoryTest extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    public function createFactory(): LinkedInTransportFactory
    {
        return new LinkedInTransportFactory();
    }

    public static function createProvider(): iterable
    {
        yield [
            'linkedin://host.test',
            'linkedin://accessToken:UserId@host.test',
        ];

        yield 'with organization author' => [
            'linkedin://host.test?author=organization',
            'linkedin://accessToken:OrganizationId@host.test?author=organization',
        ];

        yield 'with person author' => [
            'linkedin://host.test',
            'linkedin://accessToken:UserId@host.test?author=person',
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

    public function testInvalidAuthorOptionThrows()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid LinkedIn DSN author option "company". Supported values: "person", "organization".');

        $this->createFactory()->create(new Dsn('linkedin://accessToken:UserId@default?author=company'));
    }
}
