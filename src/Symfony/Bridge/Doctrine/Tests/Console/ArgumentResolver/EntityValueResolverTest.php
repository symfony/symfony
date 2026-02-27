<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Console\ArgumentResolver;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ArgumentResolver\Console\EntityValueResolver;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Console\ArgumentResolver\Exception\NearMissValueResolverException;
use Symfony\Component\Console\ArgumentResolver\ValueResolver\ValueResolverInterface;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

class EntityValueResolverTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!interface_exists(ValueResolverInterface::class)) {
            self::markTestSkipped('Requires symfony/console >= 8.1');
        }
    }

    public function testResolveWithoutArgumentAttribute()
    {
        $manager = $this->createStub(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $input = new ArrayInput(['entity' => 1], new InputDefinition([
            new InputArgument('entity'),
        ]));

        // Without #[Argument] attribute, resolver should return []
        $member = $this->createMember('entity', \stdClass::class, new MapEntity(), withArgument: false);

        $this->assertSame([], iterator_to_array($resolver->resolve('entity', $input, $member)));
    }

    public function testResolveWithoutManager()
    {
        $registry = $this->createRegistry(null);
        $resolver = new EntityValueResolver($registry);

        $input = new ArrayInput(['entity' => 1], new InputDefinition([
            new InputArgument('entity'),
        ]));

        $member = $this->createMember('entity', \stdClass::class, new MapEntity());

        $this->assertSame([], iterator_to_array($resolver->resolve('entity', $input, $member)));
    }

    public function testResolveWithNoIdAndDataOptional()
    {
        $manager = $this->createStub(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $input = new ArrayInput([], new InputDefinition([
            new InputArgument('entity'),
        ]));

        $member = $this->createMember('entity', \stdClass::class, new MapEntity(), nullable: true);

        $this->expectException(NearMissValueResolverException::class);
        $this->expectExceptionMessage('Cannot find mapping for "stdClass"');

        iterator_to_array($resolver->resolve('entity', $input, $member));
    }

    public function testResolveById()
    {
        $manager = $this->createMock(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $input = new ArrayInput(['entity' => 1], new InputDefinition([
            new InputArgument('entity'),
        ]));

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($object = new \stdClass());

        $manager->expects($this->once())
            ->method('getRepository')
            ->with(\stdClass::class)
            ->willReturn($repository);

        $member = $this->createMember('entity', \stdClass::class, new MapEntity());

        $this->assertSame([$object], iterator_to_array($resolver->resolve('entity', $input, $member)));
    }

    public function testResolveByIdWithCustomName()
    {
        $manager = $this->createMock(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $input = new ArrayInput(['user-id' => 5], new InputDefinition([
            new InputArgument('user-id'),
        ]));

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(5)
            ->willReturn($object = new \stdClass());

        $manager->expects($this->once())
            ->method('getRepository')
            ->with(\stdClass::class)
            ->willReturn($repository);

        $member = $this->createMember('entity', \stdClass::class, new MapEntity(id: 'userId'));

        $this->assertSame([$object], iterator_to_array($resolver->resolve('entity', $input, $member)));
    }

    public function testResolveByMapping()
    {
        $manager = $this->createMock(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $input = new ArrayInput(['name' => 'foo'], new InputDefinition([
            new InputArgument('name'),
        ]));

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'foo'])
            ->willReturn($object = new \stdClass());

        $manager->expects($this->once())
            ->method('getRepository')
            ->with(\stdClass::class)
            ->willReturn($repository);

        $member = $this->createMember('entity', \stdClass::class, new MapEntity(mapping: ['name']));

        $this->assertSame([$object], iterator_to_array($resolver->resolve('entity', $input, $member)));
    }

    public function testResolveNotFoundThrowsException()
    {
        $manager = $this->createMock(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $input = new ArrayInput(['entity' => 1], new InputDefinition([
            new InputArgument('entity'),
        ]));

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn(null);

        $manager->expects($this->once())
            ->method('getRepository')
            ->with(\stdClass::class)
            ->willReturn($repository);

        $member = $this->createMember('entity', \stdClass::class, new MapEntity());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('"stdClass" object not found');

        iterator_to_array($resolver->resolve('entity', $input, $member));
    }

    public function testResolveNotFoundReturnsNullWhenNullable()
    {
        $manager = $this->createMock(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $input = new ArrayInput(['entity' => 1], new InputDefinition([
            new InputArgument('entity'),
        ]));

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn(null);

        $manager->expects($this->once())
            ->method('getRepository')
            ->with(\stdClass::class)
            ->willReturn($repository);

        $member = $this->createMember('entity', \stdClass::class, new MapEntity(), nullable: true);

        $this->assertSame([null], iterator_to_array($resolver->resolve('entity', $input, $member)));
    }

    public function testResolveWithExpression()
    {
        $manager = $this->createMock(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $language = $this->createMock(ExpressionLanguage::class);
        $resolver = new EntityValueResolver($registry, $language);

        $input = new ArrayInput(['entity' => 1], new InputDefinition([
            new InputArgument('entity'),
        ]));

        $repository = $this->createMock(ObjectRepository::class);
        $repository->expects($this->never())
            ->method('find');

        $manager->expects($this->once())
            ->method('getRepository')
            ->with(\stdClass::class)
            ->willReturn($repository);

        $language->expects($this->once())
            ->method('evaluate')
            ->with('repository.findOneByCustomMethod(entity)', [
                'repository' => $repository,
                'input' => $input,
                'entity' => 1,
            ])
            ->willReturn($object = new \stdClass());

        $member = $this->createMember('entity', \stdClass::class, new MapEntity(expr: 'repository.findOneByCustomMethod(entity)'));

        $this->assertSame([$object], iterator_to_array($resolver->resolve('entity', $input, $member)));
    }

    public function testResolveWithStripNull()
    {
        $manager = $this->createMock(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $input = new ArrayInput(['entity' => null], new InputDefinition([
            new InputArgument('entity'),
        ]));

        $member = $this->createMember('entity', \stdClass::class, new MapEntity(mapping: ['entity'], stripNull: true), nullable: true);

        $manager->expects($this->never())
            ->method('getClassMetadata');

        $manager->expects($this->never())
            ->method('getRepository');

        $this->expectException(NearMissValueResolverException::class);

        iterator_to_array($resolver->resolve('entity', $input, $member));
    }

    public function testAlreadyResolved()
    {
        $manager = $this->createStub(ObjectManager::class);
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $object = new \stdClass();
        $input = new ArrayInput(['entity' => $object], new InputDefinition([
            new InputArgument('entity'),
        ]));

        $member = $this->createMember('entity', \stdClass::class, new MapEntity());

        $this->assertSame([], iterator_to_array($resolver->resolve('entity', $input, $member)));
    }

    private function createMember(string $name, string $type, ?MapEntity $entity = null, bool $nullable = false, bool $withArgument = true): ReflectionMember
    {
        $nullablePrefix = $nullable ? '?' : '';
        $argumentAttribute = $withArgument ? '#[\\Symfony\\Component\\Console\\Attribute\\Argument]' : '';
        $mapEntityAttribute = $entity ? \sprintf('#[\\Symfony\\Bridge\\Doctrine\\Attribute\\MapEntity(%s)]', $this->mapEntityToString($entity)) : '';

        $command = eval(\sprintf('return new class {
            public function __invoke(
                %s
                %s
                %s%s $%s
            ) {}
        };', $argumentAttribute, $mapEntityAttribute, $nullablePrefix, $type, $name));

        $reflection = new \ReflectionMethod($command, '__invoke');
        $parameter = $reflection->getParameters()[0];

        return new ReflectionMember($parameter);
    }

    private function mapEntityToString(MapEntity $entity): string
    {
        $parts = [];

        if (null !== $entity->id) {
            $parts[] = \sprintf('id: %s', var_export($entity->id, true));
        }
        if (null !== $entity->mapping) {
            $parts[] = \sprintf('mapping: %s', var_export($entity->mapping, true));
        }
        if (false !== $entity->stripNull) {
            $parts[] = 'stripNull: true';
        }
        if (null !== $entity->expr) {
            $parts[] = \sprintf('expr: %s', var_export($entity->expr, true));
        }

        return implode(', ', $parts);
    }

    private function createRegistry(?ObjectManager $manager = null): ManagerRegistry
    {
        $registry = $this->createStub(ManagerRegistry::class);

        $registry
            ->method('getManagerForClass')
            ->willReturn($manager);

        if (null === $manager) {
            $registry->method('getManager')
                ->willThrowException(new \InvalidArgumentException());
        } else {
            $registry->method('getManager')->willReturn($manager);
        }

        return $registry;
    }
}
