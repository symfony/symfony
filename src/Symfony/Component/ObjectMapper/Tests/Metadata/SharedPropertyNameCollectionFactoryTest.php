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
use Symfony\Component\ObjectMapper\Metadata\PropertyNameCollectionFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Metadata\SharedPropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;
use Symfony\Component\ObjectMapper\Tests\Fixtures\B;

class SharedPropertyNameCollectionFactoryTest extends TestCase
{
    public function testInstancesCreatedWithTheSameKeyShareThePropertyNamesTheyLoad()
    {
        $inner = $this->createCountingFactory();

        $names = (new SharedPropertyNameCollectionFactory($inner, __METHOD__))->create(new A());

        $this->assertSame((new ReflectionPropertyNameCollectionFactory())->create(new A()), $names);
        $this->assertSame($names, (new SharedPropertyNameCollectionFactory($inner, __METHOD__))->create(new A()));
        $this->assertSame(1, $inner->calls);
    }

    public function testInstancesCreatedWithDifferentKeysDoNotShare()
    {
        $inner = $this->createCountingFactory();

        (new SharedPropertyNameCollectionFactory($inner, __METHOD__.'1'))->create(new A());
        (new SharedPropertyNameCollectionFactory($inner, __METHOD__.'2'))->create(new A());
        (new SharedPropertyNameCollectionFactory($inner, __METHOD__.'2'))->create((new \ReflectionClass(B::class))->newInstanceWithoutConstructor());

        $this->assertSame(3, $inner->calls);
    }

    private function createCountingFactory(): PropertyNameCollectionFactoryInterface
    {
        return new class(new ReflectionPropertyNameCollectionFactory()) implements PropertyNameCollectionFactoryInterface {
            public int $calls = 0;

            public function __construct(private readonly PropertyNameCollectionFactoryInterface $inner)
            {
            }

            public function create(object $object): array
            {
                ++$this->calls;

                return $this->inner->create($object);
            }
        };
    }
}
