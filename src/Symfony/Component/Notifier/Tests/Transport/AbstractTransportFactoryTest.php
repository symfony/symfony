<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Transport;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\TransportInterface;

class AbstractTransportFactoryTest extends TestCase
{
    #[DataProvider('sslProvider')]
    public function testGetSsl(string $dsn, ?bool $expected)
    {
        $this->assertSame($expected, (new DummyTransportFactory())->exposedSsl(new Dsn($dsn)));
    }

    public static function sslProvider(): iterable
    {
        yield 'option not set' => ['dummy://host.test', null];
        yield 'true' => ['dummy://host.test?ssl=true', true];
        yield 'on' => ['dummy://host.test?ssl=on', true];
        yield '1' => ['dummy://host.test?ssl=1', true];
        yield 'false' => ['dummy://host.test?ssl=false', false];
        yield 'off' => ['dummy://host.test?ssl=off', false];
        yield '0' => ['dummy://host.test?ssl=0', false];
        yield 'empty' => ['dummy://host.test?ssl=', false];
    }
}

class DummyTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        throw new \BadMethodCallException('Not implemented.');
    }

    public function exposedSsl(Dsn $dsn): ?bool
    {
        return $this->getSsl($dsn);
    }

    protected function getSupportedSchemes(): array
    {
        return ['dummy'];
    }
}
