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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Exception\UnsupportedSchemeException;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\NullTransport;
use Symfony\Component\Notifier\Transport\NullTransportFactory;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class NullTransportFactoryTest extends TestCase
{
    /**
     * @var NullTransportFactory
     */
    private $nullTransportFactory;

    protected function setUp(): void
    {
        $this->nullTransportFactory = new NullTransportFactory(
            self::createMock(EventDispatcherInterface::class),
            self::createMock(HttpClientInterface::class)
        );
    }

    public function testCreateThrowsUnsupportedSchemeException()
    {
        self::expectException(UnsupportedSchemeException::class);

        $this->nullTransportFactory->create(new Dsn('foo://localhost'));
    }

    public function testCreate()
    {
        self::assertInstanceOf(NullTransport::class, $this->nullTransportFactory->create(new Dsn('null://null')));
    }
}
