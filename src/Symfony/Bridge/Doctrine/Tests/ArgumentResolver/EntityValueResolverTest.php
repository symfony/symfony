<?php

namespace Symfony\Bridge\Doctrine\Tests\ArgumentResolver;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
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
    public function testSupport()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $metadataFactory = $this->getMockBuilder(ClassMetadataFactory::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $registry->expects($this->once())
            ->method('getManagerNames')
            ->with()
            ->willReturn(['default' => 'default']);

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('stdClass')
            ->willReturn($manager);
        $manager->expects($this->once())
            ->method('getMetadataFactory')
            ->with()
            ->willReturn($metadataFactory);
        $metadataFactory->expects($this->once())
            ->method('isTransient')
            ->with('stdClass')
            ->willReturn(false);

        $request = new Request();
        $argument = $this->createArgument();

        $this->assertTrue($converter->supports($request, $argument));
    }

    public function testSupportWithoutRegistry()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $registry->expects($this->once())
            ->method('getManagerNames')
            ->with()
            ->willReturn([]);

        $request = new Request();
        $argument = $this->createArgument();

        $this->assertFalse($converter->supports($request, $argument));
    }

    public function testSupportWithoutClass()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $registry->expects($this->once())
            ->method('getManagerNames')
            ->with()
            ->willReturn(['default' => 'default']);

        $request = new Request();
        $argument = new ArgumentMetadata('arg', null, false, false, null);

        $this->assertFalse($converter->supports($request, $argument));
    }

    public function testSupportWithoutAttribute()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry, null, ['attribute_only' => true]);

        $registry->expects($this->once())
            ->method('getManagerNames')
            ->with()
            ->willReturn(['default' => 'default']);

        $request = new Request();
        $argument = $this->createArgument();

        $this->assertFalse($converter->supports($request, $argument));
    }

    public function testSupportWithoutManager()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $registry->expects($this->once())
            ->method('getManagerNames')
            ->with()
            ->willReturn(['default' => 'default']);

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('stdClass')
            ->willReturn(null);

        $request = new Request();
        $argument = $this->createArgument();

        $this->assertFalse($converter->supports($request, $argument));
    }

    public function testApplyWithNoIdAndData()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $this->expectException(\LogicException::class);

        $request = new Request();
        $argument = $this->createArgument(null, new MapEntity());

        $converter->resolve($request, $argument);
    }

    public function testApplyWithNoIdAndDataOptional()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $argument = $this->createArgument(null, new MapEntity(), 'arg', true);

        $ret = $converter->resolve($request, $argument);

        $this->assertYieldEquals([null], $ret);
    }

    public function testApplyWithStripNulls()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('arg', null);
        $argument = $this->createArgument('stdClass', new MapEntity(mapping: ['arg' => 'arg'], stripNull: true), 'arg', true);

        $classMetadata = $this->getMockBuilder(ClassMetadata::class)->getMock();
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with('stdClass')
            ->willReturn($classMetadata);

        $manager->expects($this->never())
            ->method('getRepository');

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('stdClass')
            ->willReturn($manager);

        $classMetadata->expects($this->once())
            ->method('hasField')
            ->with($this->equalTo('arg'))
            ->willReturn(true);

        $ret = $converter->resolve($request, $argument);

        $this->assertYieldEquals([null], $ret);
    }

    /**
     * @dataProvider idsProvider
     */
    public function testApplyWithId(string|int $id)
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('id', $id);

        $argument = $this->createArgument('stdClass', new MapEntity(id: 'id'));

        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $registry->expects($this->once())
            ->method('getManagerForClass')
              ->with('stdClass')
              ->willReturn($manager);

        $manager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($objectRepository);

        $objectRepository->expects($this->once())
            ->method('find')
            ->with($this->equalTo($id))
            ->willReturn($object = new \stdClass());

        $ret = $converter->resolve($request, $argument);

        $this->assertYieldEquals([$object], $ret);
    }

    public function testApplyWithConversionFailedException()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('id', 'test');

        $argument = $this->createArgument('stdClass', new MapEntity(id: 'id'));

        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $registry->expects($this->once())
            ->method('getManagerForClass')
              ->with('stdClass')
              ->willReturn($manager);

        $manager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($objectRepository);

        $objectRepository->expects($this->once())
            ->method('find')
            ->with($this->equalTo('test'))
                      ->will($this->throwException(new ConversionException()));

        $this->expectException(NotFoundHttpException::class);

        $converter->resolve($request, $argument);
    }

    public function testUsedProperIdentifier()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('id', 1);
        $request->attributes->set('entity_id', null);
        $request->attributes->set('arg', null);

        $argument = $this->createArgument('stdClass', new MapEntity(id: 'entity_id'), 'arg', true);

        $ret = $converter->resolve($request, $argument);

        $this->assertYieldEquals([null], $ret);
    }

    public function idsProvider(): iterable
    {
        yield [1];
        yield [0];
        yield ['foo'];
    }

    public function testApplyGuessOptional()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('arg', null);

        $argument = $this->createArgument('stdClass', new MapEntity(), 'arg', true);

        $classMetadata = $this->getMockBuilder(ClassMetadata::class)->getMock();
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with('stdClass')
            ->willReturn($classMetadata);

        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $registry->expects($this->once())
            ->method('getManagerForClass')
              ->with('stdClass')
              ->willReturn($manager);

        $manager->expects($this->never())->method('getRepository');

        $objectRepository->expects($this->never())->method('find');
        $objectRepository->expects($this->never())->method('findOneBy');

        $ret = $converter->resolve($request, $argument);

        $this->assertYieldEquals([null], $ret);
    }

    public function testApplyWithMappingAndExclude()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $request = new Request();
        $request->attributes->set('foo', 1);
        $request->attributes->set('bar', 2);

        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(mapping: ['foo' => 'Foo'], exclude: ['bar'])
        );

        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $metadata = $this->getMockBuilder(ClassMetadata::class)->getMock();
        $repository = $this->getMockBuilder(ObjectRepository::class)->getMock();

        $registry->expects($this->once())
            ->method('getManagerForClass')
                ->with('stdClass')
                ->willReturn($manager);

        $manager->expects($this->once())
            ->method('getClassMetadata')
                ->with('stdClass')
                ->willReturn($metadata);
        $manager->expects($this->once())
            ->method('getRepository')
                ->with('stdClass')
                ->willReturn($repository);

        $metadata->expects($this->once())
            ->method('hasField')
                 ->with($this->equalTo('Foo'))
                 ->willReturn(true);

        $repository->expects($this->once())
            ->method('findOneBy')
            ->with($this->equalTo(['Foo' => 1]))
            ->willReturn($object = new \stdClass());

        $ret = $converter->resolve($request, $argument);

        $this->assertYieldEquals([$object], $ret);
    }

    public function testIgnoreMappingWhenAutoMappingDisabled()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry, null, ['auto_mapping' => false]);

        $request = new Request();
        $request->attributes->set('foo', 1);

        $argument = $this->createArgument(
            'stdClass',
            new MapEntity()
        );

        $metadata = $this->getMockBuilder(ClassMetadata::class)->getMock();

        $registry->expects($this->never())
            ->method('getManagerForClass');

        $metadata->expects($this->never())
            ->method('hasField');

        $this->expectException(\LogicException::class);

        $ret = $converter->resolve($request, $argument);

        $this->assertYieldEquals([], $ret);
    }

    public function testSupports()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $argument = $this->createArgument('stdClass', new MapEntity());
        $metadataFactory = $this->getMockBuilder(ClassMetadataFactory::class)->getMock();
        $metadataFactory->expects($this->once())
            ->method('isTransient')
            ->with($this->equalTo('stdClass'))
            ->willReturn(false);

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectManager->expects($this->once())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $registry->expects($this->any())
            ->method('getManagerNames')
            ->willReturn(['default' => 'default']);

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('stdClass')
            ->willReturn($objectManager);

        $ret = $converter->supports(new Request(), $argument);

        $this->assertTrue($ret, 'Should be supported');
    }

    public function testSupportsWithConfiguredObjectManager()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $argument = $this->createArgument('stdClass', new MapEntity(objectManager: 'foo'));
        $metadataFactory = $this->getMockBuilder(ClassMetadataFactory::class)->getMock();
        $metadataFactory->expects($this->once())
            ->method('isTransient')
            ->with($this->equalTo('stdClass'))
            ->willReturn(false);

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectManager->expects($this->once())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $registry->expects($this->exactly(2))
            ->method('getManagerNames')
            ->willReturn(['foo' => 'foo']);

        $registry->expects($this->once())
            ->method('getManager')
            ->with('foo')
            ->willReturn($objectManager);

        $ret = $converter->supports(new Request(), $argument);

        $this->assertTrue($ret, 'Should be supported');
    }

    public function testSupportsWithDifferentConfiguration()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $argument = $this->createArgument('DateTime');

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectManager->expects($this->never())
            ->method('getMetadataFactory');

        $registry->expects($this->any())
            ->method('getManagerNames')
            ->willReturn(['default' => 'default']);

        $registry->expects($this->never())
            ->method('getManager');

        $ret = $converter->supports(new Request(), $argument);

        $this->assertFalse($ret, 'Should not be supported');
    }

    public function testExceptionWithExpressionIfNoLanguageAvailable()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $converter = new EntityValueResolver($registry);

        $this->expectException(\LogicException::class);

        $request = new Request();
        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(expr: 'repository.find(id)'),
            'arg1'
        );

        $converter->resolve($request, $argument);
    }

    public function testExpressionFailureReturns404()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $language = $this->getMockBuilder(ExpressionLanguage::class)->getMock();
        $converter = new EntityValueResolver($registry, $language);

        $this->expectException(NotFoundHttpException::class);

        $request = new Request();
        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(expr: 'repository.someMethod()'),
            'arg1'
        );

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();

        $objectManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($objectRepository);

        // find should not be attempted on this repository as a fallback
        $objectRepository->expects($this->never())
            ->method('find');

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $language->expects($this->once())
            ->method('evaluate')
            ->willReturn(null);

        $converter->resolve($request, $argument);
    }

    public function testExpressionMapsToArgument()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $language = $this->getMockBuilder(ExpressionLanguage::class)->getMock();
        $converter = new EntityValueResolver($registry, $language);

        $request = new Request();
        $request->attributes->set('id', 5);
        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(expr: 'repository.findOneByCustomMethod(id)'),
            'arg1'
        );

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();

        $objectManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($objectRepository);

        // find should not be attempted on this repository as a fallback
        $objectRepository->expects($this->never())
            ->method('find');

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $language->expects($this->once())
            ->method('evaluate')
            ->with('repository.findOneByCustomMethod(id)', [
                'repository' => $objectRepository,
                'id' => 5,
            ])
            ->willReturn($object = new \stdClass());

        $ret = $converter->resolve($request, $argument);
        $this->assertYieldEquals([$object], $ret);
    }

    public function testExpressionSyntaxErrorThrowsException()
    {
        $registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $language = $this->getMockBuilder(ExpressionLanguage::class)->getMock();
        $converter = new EntityValueResolver($registry, $language);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('syntax error message around position 10');

        $request = new Request();
        $argument = $this->createArgument(
            'stdClass',
            new MapEntity(expr: 'repository.findOneByCustomMethod(id)'),
            'arg1'
        );

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();

        $objectManager->expects($this->once())
            ->method('getRepository')
            ->willReturn($objectRepository);

        // find should not be attempted on this repository as a fallback
        $objectRepository->expects($this->never())
            ->method('find');

        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $language->expects($this->once())
            ->method('evaluate')
            ->will($this->throwException(new SyntaxError('syntax error message', 10)));

        $ret = $converter->resolve($request, $argument);

        $this->assertYieldEquals([null], $ret);
    }

    private function createArgument(string $class = null, MapEntity $entity = null, string $name = 'arg', bool $isNullable = false): ArgumentMetadata
    {
        return new ArgumentMetadata($name, $class ?? \stdClass::class, false, false, null, $isNullable, $entity ? [$entity] : []);
    }

    private function assertYieldEquals(array $expected, iterable $generator)
    {
        $args = [];
        foreach ($generator as $arg) {
            $args[] = $arg;
        }

        $this->assertEquals($expected, $args);
    }
}
