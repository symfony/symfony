<?php

declare(strict_types=1);

namespace Symfony\Component\Notifier\Bridge\Pusher\Tests;

use Symfony\Component\Notifier\Bridge\Pusher\PusherTransportFactory;
use Symfony\Component\Notifier\Test\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * @author Yasmany Cubela Medina <yasmanycm@gmail.com>
 *
 * @internal
 * @coversNothing
 */
final class PusherTransportFactoryTest extends TransportFactoryTestCase
{
    /**
     * @return PusherTransportFactory
     */
    public function createFactory(): TransportFactoryInterface
    {
        return new PusherTransportFactory();
    }

    public function createProvider(): iterable
    {
        yield [
            'pusher://key:secret@id?server=mt1',
            'pusher://key:secret@id?server=mt1',
        ];
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'pusher://key:secret@id?server=mt1'];
        yield [false, 'somethingElse://xoxb-TestToken@host?server=testChannel'];
    }

    public function incompleteDsnProvider(): iterable
    {
        yield 'missing secret' => ['pusher://key@id?server=mt1'];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://something@else'];
    }

    public function missingRequiredOptionProvider(): iterable
    {
        yield ['pusher://key:secret@id'];
    }
}
