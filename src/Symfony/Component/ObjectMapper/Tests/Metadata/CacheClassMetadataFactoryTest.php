<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Metadata;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\ObjectMapper\Metadata\CacheClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ClassMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;

class CacheClassMetadataFactoryTest extends TestCase
{
    public function testAMissDelegatesOnceAndStoresASingleItemForThePair()
    {
        $inner = $this->createCountingFactory();
        $pool = new ArrayAdapter();
        $factory = new CacheClassMetadataFactory($inner, $pool);
        $source = new A();
        $target = (new \ReflectionClass(B::class))->newInstanceWithoutConstructor();

        $metadata = $factory->create($source, $target);
        $factory->create($source, $target);

        $this->assertEquals((new ReflectionClassMetadataFactory(new ReflectionObjectMapperMetadataFactory()))->create($source, $target), $metadata);
        $this->assertSame(1, $inner->calls);
        $this->assertCount(1, $pool->getValues());
    }

    public function testAHitDoesNotCallTheInnerFactory()
    {
        $pool = new ArrayAdapter();
        $source = new A();
        $target = (new \ReflectionClass(B::class))->newInstanceWithoutConstructor();
        $expected = (new CacheClassMetadataFactory($this->createCountingFactory(), $pool))->create($source, $target);

        $inner = $this->createCountingFactory();
        $metadata = (new CacheClassMetadataFactory($inner, $pool))->create($source, $target);

        $this->assertEquals($expected, $metadata);
        $this->assertSame(0, $inner->calls);
    }

    private function createCountingFactory(): ClassMetadataFactoryInterface
    {
        return new class(new ReflectionClassMetadataFactory(new ReflectionObjectMapperMetadataFactory())) implements ClassMetadataFactoryInterface {
            public int $calls = 0;

            public function __construct(private readonly ClassMetadataFactoryInterface $inner)
            {
            }

            public function create(object $source, object $target): array
            {
                ++$this->calls;

                return $this->inner->create($source, $target);
            }
        };
    }
}
