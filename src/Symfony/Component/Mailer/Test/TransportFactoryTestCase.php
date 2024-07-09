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

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A test case to ease testing Transport Factory.
 *
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
abstract class TransportFactoryTestCase extends TestCase
{
    protected const USER = 'u$er';
    protected const PASSWORD = 'pa$s';

    protected EventDispatcherInterface $dispatcher;
    protected HttpClientInterface $client;
    protected LoggerInterface $logger;

    abstract public function getFactory(): TransportFactoryInterface;

    /**
     * @psalm-return iterable<array{0: Dsn, 1: bool}>
     */
    abstract public static function supportsProvider(): iterable;

    /**
     * @psalm-return iterable<array{0: Dsn, 1: TransportInterface}>
     */
    abstract public static function createProvider(): iterable;

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

    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(Dsn $dsn, bool $supports)
    {
        $factory = $this->getFactory();

        $this->assertSame($supports, $factory->supports($dsn));
    }

    /**
     * @dataProvider createProvider
     */
    public function testCreate(Dsn $dsn, TransportInterface $transport)
    {
        $factory = $this->getFactory();

        $this->assertEquals($transport, $factory->create($dsn));
        if (str_contains('smtp', $dsn->getScheme())) {
            $this->assertStringMatchesFormat($dsn->getScheme().'://%S'.$dsn->getHost().'%S', (string) $transport);
        }
    }

    /**
     * @dataProvider unsupportedSchemeProvider
     */
    public function testUnsupportedSchemeException(Dsn $dsn, ?string $message = null)
    {
        $factory = $this->getFactory();

        $this->expectException(UnsupportedSchemeException::class);
        if (null !== $message) {
            $this->expectExceptionMessage($message);
        }

        $factory->create($dsn);
    }

    /**
     * @dataProvider incompleteDsnProvider
     */
    public function testIncompleteDsnException(Dsn $dsn)
    {
        $factory = $this->getFactory();

        $this->expectException(IncompleteDsnException::class);
        $factory->create($dsn);
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
