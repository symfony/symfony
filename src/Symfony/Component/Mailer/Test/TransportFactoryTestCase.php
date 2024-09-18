<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Test;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A test case to ease testing Transport Factory.
 *
 * @author Konstantin Myakshin <molodchick@gmail.com>
 *
 * @deprecated since Symfony 7.2, use AbstractTransportFactoryTestCase instead
 */
abstract class TransportFactoryTestCase extends AbstractTransportFactoryTestCase
{
    use IncompleteDsnTestTrait;

    protected EventDispatcherInterface $dispatcher;
    protected HttpClientInterface $client;
    protected LoggerInterface $logger;

    /**
     * @psalm-return iterable<array{0: Dsn, 1?: string|null}>
     */
    public static function unsupportedSchemeProvider(): iterable
    {
        return [];
    }

    /**
     * @psalm-return iterable<array{0: Dsn}>
     */
    public static function incompleteDsnProvider(): iterable
    {
        return [];
    }

    protected function getDispatcher(): EventDispatcherInterface
    {
        return $this->dispatcher ??= $this->createMock(EventDispatcherInterface::class);
    }

    protected function getClient(): HttpClientInterface
    {
        return $this->client ??= $this->createMock(HttpClientInterface::class);
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger ??= $this->createMock(LoggerInterface::class);
    }
}
