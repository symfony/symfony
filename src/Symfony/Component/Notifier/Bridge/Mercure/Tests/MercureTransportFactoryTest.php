<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mercure\Tests;

use LogicException;
use Symfony\Bridge\PhpUnit\ClassExistsMock;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Notifier\Bridge\Mercure\MercureTransportFactory;
use Symfony\Component\Notifier\Tests\TransportFactoryTestCase;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 */
final class MercureTransportFactoryTest extends TransportFactoryTestCase
{
    public function createFactory(): TransportFactoryInterface
    {
        $publisherLocator = $this->createMock(ServiceProviderInterface::class);
        $publisherLocator->method('has')->willReturn(true);
        $publisherLocator->method('get')->willReturn($this->createMock(PublisherInterface::class));

        return new MercureTransportFactory($publisherLocator);
    }

    public function supportsProvider(): iterable
    {
        yield [true, 'mercure://publisherId?topic=topic'];
        yield [false, 'somethingElse://publisherId?topic=topic'];
    }

    public function createProvider(): iterable
    {
        yield [
            'mercure://publisherId?topic=%2Ftopic%2F1',
            'mercure://publisherId?topic=/topic/1',
        ];

        yield [
            'mercure://publisherId?topic%5B0%5D=%2Ftopic%2F1&topic%5B1%5D=%2Ftopic%2F2',
            'mercure://publisherId?topic[]=/topic/1&topic[]=/topic/2',
        ];

        yield [
            'mercure://publisherId?topic=https%3A%2F%2Fsymfony.com%2Fnotifier',
            'mercure://publisherId',
        ];
    }

    public function unsupportedSchemeProvider(): iterable
    {
        yield ['somethingElse://publisherId?topic=topic'];
    }

    public function testCreateWithEmptyServiceProviderAndWithoutMercureBundleThrows()
    {
        ClassExistsMock::register(MercureTransportFactory::class);
        ClassExistsMock::withMockedClasses([MercureBundle::class => false]);

        $publisherLocator = $this->createMock(ServiceProviderInterface::class);
        $publisherLocator->method('has')->willReturn(false);
        $publisherLocator->method('getProvidedServices')->willReturn([]);

        $factory = new MercureTransportFactory($publisherLocator);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No publishers found. Did you forget to install the MercureBundle? Try running "composer require symfony/mercure-bundle".');

        try {
            $factory->create(new Dsn('mercure://publisherId'));
        } finally {
            ClassExistsMock::withMockedClasses([MercureBundle::class => true]);
        }
    }

    public function testNotFoundPublisherThrows()
    {
        $publisherLocator = $this->createMock(ServiceProviderInterface::class);
        $publisherLocator->method('has')->willReturn(false);
        $publisherLocator->method('getProvidedServices')->willReturn(['fooPublisher' => 'fooFqcn', 'barPublisher' => 'barFqcn']);

        $factory = new MercureTransportFactory($publisherLocator);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('"publisherId" not found. Did you mean one of: fooPublisher, barPublisher?');
        $factory->create(new Dsn('mercure://publisherId'));
    }
}
