<?php

namespace Symfony\Component\Notifier\Bridge\LinkedIn\Tests;

use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransportFactory;
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
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

    public function createProvider(): iterable
    {
        yield [
            'linkedin://host.test',
            'linkedin://accessToken:UserId@host.test',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'linkedin://host'];
        yield [false, 'somethingElse://host'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing account or user_id' => ['linkedin://AccessTokenOrUserId@default'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://accessToken:UserId@default'];
    }
}
