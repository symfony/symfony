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
use Symfony\Component\ObjectMapper\Metadata\ClassMetadataFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionClassMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\ReflectionObjectMapperMetadataFactory;
use Symfony\Component\ObjectMapper\Metadata\SharedClassMetadataFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;

class SharedClassMetadataFactoryTest extends TestCase
{
    public function testInstancesCreatedWithTheSameKeyShareTheMetadataTheyLoad()
    {
        $inner = $this->createCountingFactory();
        [$source, $target] = $this->createPair();

        $metadata = (new SharedClassMetadataFactory($inner, __METHOD__))->create($source, $target);

        $this->assertEquals((new ReflectionClassMetadataFactory(new ReflectionObjectMapperMetadataFactory()))->create($source, $target), $metadata);
        $this->assertSame($metadata, (new SharedClassMetadataFactory($inner, __METHOD__))->create(new A(), $target));
        $this->assertSame(1, $inner->calls);
    }

    public function testInstancesCreatedWithDifferentKeysDoNotShare()
    {
        $inner = $this->createCountingFactory();
        [$source, $target] = $this->createPair();

        (new SharedClassMetadataFactory($inner, __METHOD__.'1'))->create($source, $target);
        (new SharedClassMetadataFactory($inner, __METHOD__.'2'))->create($source, $target);
        (new SharedClassMetadataFactory($inner, __METHOD__.'2'))->create($target, $source);

        $this->assertSame(3, $inner->calls);
    }

    /**
     * @return array{A, B}
     */
    private function createPair(): array
    {
        return [new A(), (new \ReflectionClass(B::class))->newInstanceWithoutConstructor()];
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
