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
use Symfony\Component\Notifier\Exception\IncompleteDsnException;
use Symfony\Component\Notifier\Transport\AbstractTransportFactory;
use Symfony\Component\Notifier\Transport\Dsn;
use Symfony\Component\Notifier\Transport\NullTransport;
use Symfony\Component\Notifier\Transport\TransportInterface;

class AbstractTransportFactoryTest extends TestCase
{
    public function testMissingPasswordDoesNotLeakCredentials()
    {
        $factory = new class extends AbstractTransportFactory {
            public function create(Dsn $dsn): TransportInterface
            {
                $this->getPassword($dsn);

                return new NullTransport();
            }

            protected function getSupportedSchemes(): array
            {
                return ['foo'];
            }
        };

        try {
            $factory->create(new Dsn('foo://s3cr3t@default?token=s3cr3t'));
            $this->fail('An exception should have been thrown.');
        } catch (IncompleteDsnException $e) {
            $this->assertSame('Invalid "foo://default" notifier DSN: Password is not set.', $e->getMessage());
            $this->assertStringNotContainsString('s3cr3t', $e->getMessage());
        }
    }
}
