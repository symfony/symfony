<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface;
use Symfony\Component\Notifier\Transport\TransportInterface;

abstract class AbstractTransportFactoryTestCase extends TestCase
{
    abstract public function createFactory(): TransportFactoryInterface;

    /**
     * @return iterable<array{0: bool, 1: string}>
     */
    abstract public static function supportsProvider(): iterable;

    /**
     * @return iterable<array{0: string, 1: string, 2: TransportInterface}>
     */
    abstract public static function createProvider(): iterable;

    /**
     * @return iterable<array{0: string, 1?: string|null}>
     */
    abstract public static function unsupportedSchemeProvider(): iterable;

    /**
     * @dataProvider supportsProvider
     */
    #[DataProvider('supportsProvider')]
    public function testSupports(bool $expected, string $dsn)
    {
        $factory = $this->createFactory();

        $this->assertSame($expected, $factory->supports(new Dsn($dsn)));
    }

    /**
     * @dataProvider createProvider
     */
    #[DataProvider('createProvider')]
    public function testCreate(string $expected, string $dsn)
    {
        $factory = $this->createFactory();
        $transport = $factory->create(new Dsn($dsn));

        $this->assertSame($expected, (string) $transport);
    }

    /**
     * @dataProvider unsupportedSchemeProvider
     */
    #[DataProvider('unsupportedSchemeProvider')]
    public function testUnsupportedSchemeException(string $dsn, ?string $message = null)
    {
        $factory = $this->createFactory();

        $dsn = new Dsn($dsn);

        $this->expectException(UnsupportedSchemeException::class);
        if (null !== $message) {
            $this->expectExceptionMessage($message);
        }

        $factory->create($dsn);
    }
}
