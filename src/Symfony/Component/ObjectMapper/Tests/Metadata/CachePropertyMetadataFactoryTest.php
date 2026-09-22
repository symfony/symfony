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
use Symfony\Component\ObjectMapper\Metadata\CachePropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\PropertyMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;

class CachePropertyMetadataFactoryTest extends TestCase
{
    public function testAMissResolvesEveryPropertyOfBothClassesOnceAndStoresASingleItemForThePair()
    {
        $inner = $this->createCountingFactory();
        $pool = new ArrayAdapter();
        $factory = new CachePropertyMetadataFactory($inner, $pool, new ReflectionPropertyNameCollectionFactory());
        [$source, $target] = $this->createPair();

        $metadata = $factory->create($source, $target, 'foo');
        $factory->create($source, $target, 'baz');
        $factory->create($source, $target, 'id');

        $this->assertEquals($this->createReflectionFactory()->create($source, $target, 'foo'), $metadata);
        $this->assertSame(\count($this->propertyNamesOfThePair()), $inner->calls);
        $this->assertCount(1, $pool->getValues());
    }

    public function testAHitDoesNotCallTheInnerFactory()
    {
        $pool = new ArrayAdapter();
        [$source, $target] = $this->createPair();
        $expected = (new CachePropertyMetadataFactory($this->createCountingFactory(), $pool, new ReflectionPropertyNameCollectionFactory()))->create($source, $target, 'foo');

        $inner = $this->createCountingFactory();
        $factory = new CachePropertyMetadataFactory($inner, $pool, new ReflectionPropertyNameCollectionFactory());

        $this->assertEquals($expected, $factory->create($source, $target, 'foo'));
        $this->assertEquals($this->createReflectionFactory()->create($source, $target, 'id'), $factory->create($source, $target, 'id'));
        $this->assertSame(0, $inner->calls);
    }

    public function testAPropertyDeclaredByNeitherClassFallsBackToTheInnerFactoryWithoutGrowingThePool()
    {
        $inner = $this->createCountingFactory();
        $pool = new ArrayAdapter();
        $factory = new CachePropertyMetadataFactory($inner, $pool, new ReflectionPropertyNameCollectionFactory());
        [$source, $target] = $this->createPair();

        $factory->create($source, $target, 'foo');
        $calls = $inner->calls;
        $metadata = $factory->create($source, $target, 'undeclared');
        $factory->create($source, $target, 'undeclared');

        $this->assertEquals($this->createReflectionFactory()->create($source, $target, 'undeclared'), $metadata);
        $this->assertSame($calls + 1, $inner->calls);
        $this->assertCount(1, $pool->getValues());
    }

    /**
     * @return array{A, B}
     */
    private function createPair(): array
    {
        return [new A(), (new \ReflectionClass(B::class))->newInstanceWithoutConstructor()];
    }

    /**
     * @return list<string>
     */
    private function propertyNamesOfThePair(): array
    {
        $names = new ReflectionPropertyNameCollectionFactory();
        [$source, $target] = $this->createPair();

        return array_values(array_unique([...$names->create($source), ...$names->create($target)]));
    }

    private function createReflectionFactory(): ReflectionPropertyMetadataFactory
    {
        $metadataFactory = new ReflectionObjectMapperMetadataFactory();

        return new ReflectionPropertyMetadataFactory($metadataFactory, new ReflectionClassMetadataFactory($metadataFactory));
    }

    private function createCountingFactory(): PropertyMetadataFactoryInterface
    {
        return new class($this->createReflectionFactory()) implements PropertyMetadataFactoryInterface {
            public int $calls = 0;

            public function __construct(private readonly PropertyMetadataFactoryInterface $inner)
            {
            }

            public function create(object $source, object $target, string $property): array
            {
                ++$this->calls;

                return $this->inner->create($source, $target, $property);
            }
        };
    }
}
