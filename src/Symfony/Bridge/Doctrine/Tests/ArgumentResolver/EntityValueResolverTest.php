<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\ArgumentResolver;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\ArgumentResolver\EntityValueResolver;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntityValueResolverTest extends TestCase
{
    public function testResolveWithoutClass()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $argument = new ArgumentMetadata('arg', null, false, false, null);

        $this->assertSame([], $resolver->resolve($request, $argument));
    }

    public function testResolveWithoutAttribute()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry, null, new MapEntity(disabled: true));

        $request = new Request();
        $argument = $this->createArgument();

        $this->assertSame([], $resolver->resolve($request, $argument));
    }

    public function testResolveWithoutManager()
    {
        $registry = $this->createRegistry(null);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $argument = $this->createArgument();

        $this->assertSame([], $resolver->resolve($request, $argument));
    }

    public function testResolveWithNoIdAndDataOptional()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $argument = $this->createArgument(null, new MapEntity(), 'arg', true);

        $this->assertSame([], $resolver->resolve($request, $argument));
    }

    public function testResolveWithStripNulls()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('arg', null);
        $argument = $this->createArgument('stdClass', new MapEntity(stripNull: true), 'arg', true);

        $metadata = $this->getMockBuilder(ClassMetadata::class)->getMock();
        $metadata->expects($this->once())
            ->method('hasField')
            ->with('arg')
            ->willReturn(true);

        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with('stdClass')
            ->willReturn($metadata);

        $manager->expects($this->never())
            ->method('getRepository');

        $this->assertSame([], $resolver->resolve($request, $argument));
    }

    /**
     * @dataProvider idsProvider
     */
    public function testResolveWithId(string|int $id)
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('id', $id);

        $argument = $this->createArgument('stdClass', new MapEntity(id: 'id'));

        $repository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $repository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($object = new \stdClass());

        $manager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($repository);

        $this->assertSame([$object], $resolver->resolve($request, $argument));
    }

    public function testResolveWithNullId()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('id', null);

        $argument = $this->createArgument(isNullable: true);

        $this->assertSame([null], $resolver->resolve($request, $argument));
    }

    public function testResolveWithConversionFailedException()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('id', 'test');

        $argument = $this->createArgument('stdClass', new MapEntity(id: 'id'));

        $repository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $repository->expects($this->once())
            ->method('find')
            ->with('test')
            ->will($this->throwException(new ConversionException()));

        $manager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($repository);

        $this->expectException(NotFoundHttpException::class);

        $resolver->resolve($request, $argument);
    }

    public function testUsedProperIdentifier()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('id', 1);
        $request->attributes->set('entity_id', null);
        $request->attributes->set('arg', null);

        $argument = $this->createArgument('stdClass', new MapEntity(id: 'entity_id'), 'arg', true);

        $this->assertSame([null], $resolver->resolve($request, $argument));
    }

    public static function idsProvider(): iterable
    {
        yield [1];
        yield [0];
        yield ['foo'];
    }

    public function testResolveGuessOptional()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('guess', null);

        $argument = $this->createArgument('stdClass', new MapEntity(), 'arg', true);

        $metadata = $this->getMockBuilder(ClassMetadata::class)->getMock();
        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with('stdClass')
            ->willReturn($metadata);

        $manager->expects($this->never())->method('getRepository');

        $this->assertSame([], $resolver->resolve($request, $argument));
    }

    public function testResolveWithMappingAndExclude()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('foo', 1);
        $request->attributes->set('bar', 2);

        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(mapping: ['foo' => 'Foo'], exclude: ['bar'])
        );

        $metadata = $this->getMockBuilder(ClassMetadata::class)->getMock();
        $metadata->expects($this->once())
            ->method('hasField')
            ->with('Foo')
            ->willReturn(true);

        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with('stdClass')
            ->willReturn($metadata);

        $repository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['Foo' => 1])
            ->willReturn($object = new \stdClass());

        $manager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($repository);

        $this->assertSame([$object], $resolver->resolve($request, $argument));
    }

    public function testExceptionWithExpressionIfNoLanguageAvailable()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(expr: 'repository.find(id)'),
            'arg1'
        );

        $this->expectException(\LogicException::class);

        $resolver->resolve($request, $argument);
    }

    public function testExpressionFailureReturns404()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $language = $this->getMockBuilder(ExpressionLanguage::class)->getMock();
        $resolver = new EntityValueResolver($registry, $language);

        $this->expectException(NotFoundHttpException::class);

        $request = new Request();
        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(expr: 'repository.someMethod()'),
            'arg1'
        );

        $repository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        // find should not be attempted on this repository as a fallback
        $repository->expects($this->never())
            ->method('find');

        $manager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $language->expects($this->once())
            ->method('evaluate')
            ->willReturn(null);

        $resolver->resolve($request, $argument);
    }

    public function testExpressionMapsToArgument()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $language = $this->getMockBuilder(ExpressionLanguage::class)->getMock();
        $resolver = new EntityValueResolver($registry, $language);

        $request = new Request();
        $request->attributes->set('id', 5);
        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(expr: 'repository.findOneByCustomMethod(id)'),
            'arg1'
        );

        $repository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        // find should not be attempted on this repository as a fallback
        $repository->expects($this->never())
            ->method('find');

        $manager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $language->expects($this->once())
            ->method('evaluate')
            ->with('repository.findOneByCustomMethod(id)', [
                'repository' => $repository,
                'id' => 5,
            ])
            ->willReturn($object = new \stdClass());

        $this->assertSame([$object], $resolver->resolve($request, $argument));
    }

    public function testExpressionSyntaxErrorThrowsException()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $language = $this->getMockBuilder(ExpressionLanguage::class)->getMock();
        $resolver = new EntityValueResolver($registry, $language);

        $request = new Request();
        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(expr: 'repository.findOneByCustomMethod(id)'),
            'arg1'
        );

        $repository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        // find should not be attempted on this repository as a fallback
        $repository->expects($this->never())
            ->method('find');

        $manager->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $language->expects($this->once())
            ->method('evaluate')
            ->will($this->throwException(new SyntaxError('syntax error message', 10)));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('syntax error message around position 10');
        $resolver->resolve($request, $argument);
    }

    public function testAlreadyResolved()
    {
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $registry = $this->createRegistry($manager);
        $resolver = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('arg', new \stdClass());

        $argument = $this->createArgument('stdClass', name: 'arg');

        $this->assertSame([], $resolver->resolve($request, $argument));
    }

    private function createArgument(string $class = null, MapEntity $entity = null, string $name = 'arg', bool $isNullable = false): ArgumentMetadata
    {
        return new ArgumentMetadata($name, $class ?? \stdClass::class, false, false, null, $isNullable, $entity ? [$entity] : []);
    }

    private function createRegistry(ObjectManager $manager = null): ManagerRegistry&MockObject
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();

        $registry->expects($this->any())
            ->method('getManagerForClass')
            ->willReturn($manager);

        $registry->expects($this->any())
            ->method('getManager')
            ->willReturn($manager);

        return $registry;
    }
}
