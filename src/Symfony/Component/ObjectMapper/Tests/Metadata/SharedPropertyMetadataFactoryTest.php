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
use Symfony\Component\ObjectMapper\Metadata\PropertyMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\SharedPropertyMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;

class SharedPropertyMetadataFactoryTest extends TestCase
{
    public function testInstancesCreatedWithTheSameKeyShareTheMetadataTheyLoad()
    {
        $inner = $this->createCountingFactory();
        [$source, $target] = $this->createPair();

        $metadata = (new SharedPropertyMetadataFactory($inner, __METHOD__))->create($source, $target, 'foo');

        $this->assertEquals($this->createReflectionFactory()->create($source, $target, 'foo'), $metadata);
        $this->assertSame($metadata, (new SharedPropertyMetadataFactory($inner, __METHOD__))->create(new A(), $target, 'foo'));
        $this->assertSame(1, $inner->calls);
    }

    public function testInstancesCreatedWithDifferentKeysDoNotShare()
    {
        $inner = $this->createCountingFactory();
        [$source, $target] = $this->createPair();

        (new SharedPropertyMetadataFactory($inner, __METHOD__.'1'))->create($source, $target, 'foo');
        (new SharedPropertyMetadataFactory($inner, __METHOD__.'2'))->create($source, $target, 'foo');
        (new SharedPropertyMetadataFactory($inner, __METHOD__.'2'))->create($source, $target, 'baz');

        $this->assertSame(3, $inner->calls);
    }

    /**
     * @return array{A, B}
     */
    private function createPair(): array
    {
        return [new A(), (new \ReflectionClass(B::class))->newInstanceWithoutConstructor()];
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
