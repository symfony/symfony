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
use Symfony\Component\ObjectMapper\Metadata\CachePropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Metadata\PropertyNameCollectionFactoryInterface;
use Symfony\Component\ObjectMapper\Metadata\ReflectionPropertyNameCollectionFactory;
use Symfony\Component\ObjectMapper\Tests\Fixtures\A;

class CachePropertyNameCollectionFactoryTest extends TestCase
{
    public function testAMissDelegatesOnceAndStoresASingleItemForTheClass()
    {
        $inner = $this->createCountingFactory();
        $pool = new ArrayAdapter();
        $factory = new CachePropertyNameCollectionFactory($inner, $pool);

        $names = $factory->create(new A());
        $factory->create(new A());

        $this->assertSame((new ReflectionPropertyNameCollectionFactory())->create(new A()), $names);
        $this->assertSame(1, $inner->calls);
        $this->assertCount(1, $pool->getValues());
    }

    public function testAHitDoesNotCallTheInnerFactory()
    {
        $pool = new ArrayAdapter();
        $expected = (new CachePropertyNameCollectionFactory($this->createCountingFactory(), $pool))->create(new A());

        $inner = $this->createCountingFactory();
        $names = (new CachePropertyNameCollectionFactory($inner, $pool))->create(new A());

        $this->assertSame($expected, $names);
        $this->assertSame(0, $inner->calls);
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
