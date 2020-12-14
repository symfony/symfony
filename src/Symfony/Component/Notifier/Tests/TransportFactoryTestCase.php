<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;

/**
 * A test case to ease testing a notifier transport factory.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
abstract class TransportFactoryTestCase extends TestCase
{
    abstract public function createFactory(): TransportFactoryInterface;

    /**
     * @return iterable<array{0: bool, 1: string}>
     */
    abstract public function supportsProvider(): iterable;

    /**
     * @return iterable<array{0: string, 1: string, 2: TransportInterface}>
     */
    abstract public function createProvider(): iterable;

    /**
     * @return iterable<array{0: string, 1: string|null}>
     */
    public function unsupportedSchemeProvider(): iterable
    {
        return [];
    }

    /**
     * @return iterable<array{0: string, 1: string|null}>
     */
    public function incompleteDsnProvider(): iterable
    {
        return [];
    }

    /**
     * @dataProvider supportsProvider
     */
    public function testSupports(bool $expected, string $dsn): void
    {
        $factory = $this->createFactory();

        $this->assertSame($expected, $factory->supports(Dsn::fromString($dsn)));
    }

    /**
     * @dataProvider createProvider
     */
    public function testCreate(string $expected, string $dsn): void
    {
        $factory = $this->createFactory();
        $transport = $factory->create(Dsn::fromString($dsn));

        $this->assertSame($expected, (string) $transport);
    }

    /**
     * @dataProvider unsupportedSchemeProvider
     */
    public function testUnsupportedSchemeException(string $dsn, string $message = null): void
    {
        $factory = $this->createFactory();

        $dsn = Dsn::fromString($dsn);

        $this->expectException(UnsupportedSchemeException::class);
        if (null !== $message) {
            $this->expectExceptionMessage($message);
        }

        $factory->create($dsn);
    }

    /**
     * @dataProvider incompleteDsnProvider
     */
    public function testIncompleteDsnException(string $dsn, string $message = null): void
    {
        $factory = $this->createFactory();

        $dsn = Dsn::fromString($dsn);

        $this->expectException(IncompleteDsnException::class);
        if (null !== $message) {
            $this->expectExceptionMessage($message);
        }

        $factory->create($dsn);
    }
}
