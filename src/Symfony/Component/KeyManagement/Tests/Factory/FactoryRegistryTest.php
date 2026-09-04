<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Symfony\Component\KeyManagement\DecrypterInterface;
use Symfony\Component\KeyManagement\Dsn;
use Symfony\Component\KeyManagement\EncrypterInterface;
use Symfony\Component\KeyManagement\Exception\UnsupportedSchemeException;
use Symfony\Component\KeyManagement\Factory\FactoryRegistry;
use Symfony\Component\KeyManagement\Factory\KmsFactoryInterface;
use Symfony\Component\KeyManagement\Test\InMemoryKms;

class FactoryRegistryTest extends TestCase
{
    public function testFromStringDispatchesToTheFirstSupportingFactory()
    {
        $expectedKms = new InMemoryKms();
        $supporting = new class($expectedKms) implements KmsFactoryInterface {
            public function __construct(private readonly EncrypterInterface&DecrypterInterface $kms)
            {
            }

            public function supports(Dsn $dsn): bool
            {
                return 'memory' === $dsn->scheme;
            }

            public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
            {
                return $this->kms;
            }
        };

        $registry = new FactoryRegistry([self::nonSupporting(), $supporting]);

        $this->assertSame($expectedKms, $registry->fromString('memory://?keys[main]=00'));
    }

    public function testCreateDispatchesByDsnObject()
    {
        $expectedKms = new InMemoryKms();
        $supporting = new class($expectedKms) implements KmsFactoryInterface {
            public function __construct(private readonly EncrypterInterface&DecrypterInterface $kms)
            {
            }

            public function supports(Dsn $dsn): bool
            {
                return true;
            }

            public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
            {
                return $this->kms;
            }
        };

        $this->assertSame(
            $expectedKms,
            (new FactoryRegistry([$supporting]))->create(new Dsn('foo')),
        );
    }

    public function testSupportsReportsTrueWhenAnyFactoryMatches()
    {
        $supporting = new class implements KmsFactoryInterface {
            public function supports(Dsn $dsn): bool
            {
                return 'memory' === $dsn->scheme;
            }

            public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
            {
                throw new \LogicException();
            }
        };

        $registry = new FactoryRegistry([self::nonSupporting(), $supporting]);

        $this->assertTrue($registry->supports(new Dsn('memory')));
        $this->assertFalse($registry->supports(new Dsn('other')));
    }

    public function testNoMatchingFactoryThrowsUnsupportedScheme()
    {
        $registry = new FactoryRegistry([self::nonSupporting()]);

        $this->expectException(UnsupportedSchemeException::class);
        $this->expectExceptionMessage('"unknown"');
        $registry->fromString('unknown://anywhere');
    }

    public function testEmptyFactoryListThrowsUnsupportedScheme()
    {
        $registry = new FactoryRegistry([]);

        $this->expectException(UnsupportedSchemeException::class);
        $registry->fromString('sodium://?keys[main]=00');
    }

    private static function nonSupporting(): KmsFactoryInterface
    {
        return new class implements KmsFactoryInterface {
            public function supports(Dsn $dsn): bool
            {
                return false;
            }

            public function create(Dsn $dsn): EncrypterInterface&DecrypterInterface
            {
                throw new \LogicException('should not be called');
            }
        };
    }
}
