<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Controller;

use Doctrine\DBAL\Types\ConversionException;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Controller\EntityParamConverter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\ParamConverter;

class EntityParamConverterTest extends TestCase
{
    /**
     * @var ManagerRegistry
     */
    private $registry;

    /**
     * @var ExpressionLanguage
     */
    private $language;

    /**
     * @var EntityParamConverter
     */
    private $converter;

    protected function setUp(): void
    {
        $this->registry = $this->getMockBuilder(ManagerRegistry::class)->getMock();
        $this->language = $this->getMockBuilder(ExpressionLanguage::class)->getMock();
        $this->converter = new EntityParamConverter($this->registry, $this->language);
    }

    public function createConfiguration($class = null, array $options = null, $name = 'arg', $isOptional = false)
    {
        $methods = ['getClass', 'getAliasName', 'getOptions', 'getName', 'allowArray'];
        if (null !== $isOptional) {
            $methods[] = 'isOptional';
        }
        $config = $this
            ->getMockBuilder(ParamConverter::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
        if (null !== $options) {
            $config->expects($this->once())
                ->method('getOptions')
                ->willReturn($options);
        }
        if (null !== $class) {
            $config->expects($this->any())
                ->method('getClass')
                ->willReturn($class);
        }
        $config->expects($this->any())
            ->method('getName')
            ->willReturn($name);
        if (null !== $isOptional) {
            $config->expects($this->any())
                ->method('isOptional')
                ->willReturn($isOptional);
        }

        return $config;
    }

    public function testApplyWithNoIdAndData()
    {
        $this->expectException(\LogicException::class);

        $request = new Request();
        $config = $this->createConfiguration(null, []);
        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();

        $this->converter->apply($request, $config);
    }

    public function testApplyWithNoIdAndDataOptional()
    {
        $request = new Request();
        $config = $this->createConfiguration(null, [], 'arg', true);
        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertNull($request->attributes->get('arg'));
    }

    public function testApplyWithStripNulls()
    {
        $request = new Request();
        $request->attributes->set('arg', null);
        $config = $this->createConfiguration('stdClass', ['mapping' => ['arg' => 'arg'], 'strip_null' => true], 'arg', true);

        $classMetadata = $this->getMockBuilder(ClassMetadata::class)->getMock();
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with('stdClass')
            ->willReturn($classMetadata);

        $manager->expects($this->never())
            ->method('getRepository');

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('stdClass')
            ->willReturn($manager);

        $classMetadata->expects($this->once())
            ->method('hasField')
            ->with($this->equalTo('arg'))
            ->willReturn(true);

        $this->converter->apply($request, $config);

        $this->assertNull($request->attributes->get('arg'));
    }

    /**
     * @dataProvider idsProvider
     */
    public function testApplyWithId($id)
    {
        $request = new Request();
        $request->attributes->set('id', $id);

        $config = $this->createConfiguration('stdClass', ['id' => 'id'], 'arg');

        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $this->registry->expects($this->once())
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

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertSame($object, $request->attributes->get('arg'));
    }

    public function testApplyWithConversionFailedException()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $request = new Request();
        $request->attributes->set('id', 'test');

        $config = $this->createConfiguration('stdClass', ['id' => 'id'], 'arg');

        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $this->registry->expects($this->once())
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

        $this->converter->apply($request, $config);
    }

    public function testUsedProperIdentifier()
    {
        $request = new Request();
        $request->attributes->set('id', 1);
        $request->attributes->set('entity_id', null);
        $request->attributes->set('arg', null);

        $config = $this->createConfiguration('stdClass', ['id' => 'entity_id'], 'arg', null);

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertNull($request->attributes->get('arg'));
    }

    public function idsProvider()
    {
        return [
            [1],
            [0],
            ['foo'],
        ];
    }

    public function testApplyGuessOptional()
    {
        $request = new Request();
        $request->attributes->set('arg', null);

        $config = $this->createConfiguration('stdClass', [], 'arg', null);

        $classMetadata = $this->getMockBuilder(ClassMetadata::class)->getMock();
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $manager->expects($this->once())
            ->method('getClassMetadata')
            ->with('stdClass')
            ->willReturn($classMetadata);

        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('stdClass')
            ->willReturn($manager);

        $manager->expects($this->never())->method('getRepository');

        $objectRepository->expects($this->never())->method('find');
        $objectRepository->expects($this->never())->method('findOneBy');

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertNull($request->attributes->get('arg'));
    }

    public function testApplyWithMappingAndExclude()
    {
        $request = new Request();
        $request->attributes->set('foo', 1);
        $request->attributes->set('bar', 2);

        $config = $this->createConfiguration(
            'stdClass',
            ['mapping' => ['foo' => 'Foo'], 'exclude' => ['bar']],
            'arg'
        );

        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $metadata = $this->getMockBuilder(ClassMetadata::class)->getMock();
        $repository = $this->getMockBuilder(ObjectRepository::class)->getMock();

        $this->registry->expects($this->once())
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

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertSame($object, $request->attributes->get('arg'));
    }

    /**
     * @group legacy
     */
    public function testApplyWithRepositoryMethod()
    {
        $request = new Request();
        $request->attributes->set('id', 1);

        $config = $this->createConfiguration(
            'stdClass',
            ['repository_method' => 'getClassName'],
            'arg'
        );

        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $manager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $manager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($objectRepository);
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($manager);

        $objectRepository->expects($this->once())
            ->method('getClassName')
            ->willReturn($className = 'ObjectRepository');

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertSame($className, $request->attributes->get('arg'));
    }

    /**
     * @group legacy
     */
    public function testApplyWithRepositoryMethodAndMapping()
    {
        $request = new Request();
        $request->attributes->set('id', 1);

        $config = $this->createConfiguration(
            'stdClass',
            ['repository_method' => 'getClassName', 'mapping' => ['foo' => 'Foo']],
            'arg'
        );

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = $this->getMockBuilder(ObjectRepository::class)->getMock();
        $metadata = $this->getMockBuilder(ClassMetadata::class)->getMock();

        $objectManager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($objectRepository);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $metadata->expects($this->once())
            ->method('hasField')
            ->with($this->equalTo('Foo'))
            ->willReturn(true);

        $objectManager->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($metadata);
        $objectManager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($objectRepository);

        $objectRepository->expects($this->once())
            ->method('getClassName')
            ->willReturn($className = 'ObjectRepository');

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertSame($className, $request->attributes->get('arg'));
    }

    /**
     * @group legacy
     */
    public function testApplyWithRepositoryMethodAndMapMethodSignature()
    {
        $request = new Request();
        $request->attributes->set('first_name', 'Fabien');
        $request->attributes->set('last_name', 'Potencier');

        $config = $this->createConfiguration(
            'stdClass',
            [
                'repository_method' => 'findByFullName',
                'mapping' => ['first_name' => 'firstName', 'last_name' => 'lastName'],
                'map_method_signature' => true,
            ],
            'arg'
        );

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = new TestUserRepository();
        $metadata = $this->getMockBuilder(ClassMetadata::class)->getMock();

        $objectManager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($objectRepository);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $objectManager->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $ret = $this->converter->apply($request, $config);

        $this->assertTrue($ret);
        $this->assertSame('Fabien Potencier', $request->attributes->get('arg'));
    }

    /**
     * @group legacy
     */
    public function testApplyWithRepositoryMethodAndMapMethodSignatureException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository method "Symfony\\Bridge\\Doctrine\\Tests\\Controller\\TestUserRepository::findByFullName" requires that you provide a value for the "$lastName" argument.');

        $request = new Request();
        $request->attributes->set('first_name', 'Fabien');
        $request->attributes->set('last_name', 'Potencier');

        $config = $this->createConfiguration(
            'stdClass',
            [
                'repository_method' => 'findByFullName',
                'mapping' => ['first_name' => 'firstName', 'last_name' => 'lastNameXxx'],
                'map_method_signature' => true,
            ],
            'arg'
        );

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectRepository = new TestUserRepository();
        $metadata = $this->getMockBuilder(ClassMetadata::class)->getMock();

        $objectManager->expects($this->once())
            ->method('getRepository')
            ->with('stdClass')
            ->willReturn($objectRepository);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $objectManager->expects($this->once())
            ->method('getClassMetadata')
            ->willReturn($metadata);

        $this->converter->apply($request, $config);
    }

    public function testSupports()
    {
        $config = $this->createConfiguration('stdClass', []);
        $metadataFactory = $this->getMockBuilder('Doctrine\Persistence\Mapping\ClassMetadataFactory')->getMock();
        $metadataFactory->expects($this->once())
            ->method('isTransient')
            ->with($this->equalTo('stdClass'))
            ->willReturn(false);

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectManager->expects($this->once())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $this->registry->expects($this->any())
            ->method('getManagerNames')
            ->willReturn(['default']);

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with('stdClass')
            ->willReturn($objectManager);

        $ret = $this->converter->supports($config);

        $this->assertTrue($ret, 'Should be supported');
    }

    public function testSupportsWithConfiguredEntityManager()
    {
        $config = $this->createConfiguration('stdClass', ['entity_manager' => 'foo']);
        $metadataFactory = $this->getMockBuilder('Doctrine\Persistence\Mapping\ClassMetadataFactory')->getMock();
        $metadataFactory->expects($this->once())
            ->method('isTransient')
            ->with($this->equalTo('stdClass'))
            ->willReturn(false);

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectManager->expects($this->once())
            ->method('getMetadataFactory')
            ->willReturn($metadataFactory);

        $this->registry->expects($this->once())
            ->method('getManagerNames')
            ->willReturn(['default']);

        $this->registry->expects($this->once())
            ->method('getManager')
            ->with('foo')
            ->willReturn($objectManager);

        $ret = $this->converter->supports($config);

        $this->assertTrue($ret, 'Should be supported');
    }

    public function testSupportsWithDifferentConfiguration()
    {
        $config = $this->createConfiguration('DateTime', ['format' => \DateTime::ISO8601]);

        $objectManager = $this->getMockBuilder(ObjectManager::class)->getMock();
        $objectManager->expects($this->never())
            ->method('getMetadataFactory');

        $this->registry->expects($this->any())
            ->method('getManagerNames')
            ->willReturn(['default']);

        $this->registry->expects($this->never())
            ->method('getManager');

        $ret = $this->converter->supports($config);

        $this->assertFalse($ret, 'Should not be supported');
    }

    public function testExceptionWithExpressionIfNoLanguageAvailable()
    {
        $this->expectException(\LogicException::class);

        $request = new Request();
        $config = $this->createConfiguration(
            'stdClass',
            [
                'expr' => 'repository.find(id)',
            ],
            'arg1'
        );

        $converter = new EntityParamConverter($this->registry);
        $converter->apply($request, $config);
    }

    public function testExpressionFailureReturns404()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        $request = new Request();
        $config = $this->createConfiguration(
            'stdClass',
            [
                'expr' => 'repository.someMethod()',
            ],
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

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $this->language->expects($this->once())
            ->method('evaluate')
            ->willReturn(null);

        $this->converter->apply($request, $config);
    }

    public function testExpressionMapsToArgument()
    {
        $request = new Request();
        $request->attributes->set('id', 5);
        $config = $this->createConfiguration(
            'stdClass',
            [
                'expr' => 'repository.findOneByCustomMethod(id)',
            ],
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

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $this->language->expects($this->once())
            ->method('evaluate')
            ->with('repository.findOneByCustomMethod(id)', [
                'repository' => $objectRepository,
                'id' => 5,
            ])
            ->willReturn('new_mapped_value');

        $this->converter->apply($request, $config);
        $this->assertEquals('new_mapped_value', $request->attributes->get('arg1'));
    }

    public function testExpressionSyntaxErrorThrowsException()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('syntax error message around position 10');

        $request = new Request();
        $config = $this->createConfiguration(
            'stdClass',
            [
                'expr' => 'repository.findOneByCustomMethod(id)',
            ],
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

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->willReturn($objectManager);

        $this->language->expects($this->once())
            ->method('evaluate')
            ->will($this->throwException(new SyntaxError('syntax error message', 10)));

        $this->converter->apply($request, $config);
    }

    public function testInvalidOptionThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);

        $configuration = new ParamConverter(
            name: 'foo',
            options: [
                'fake_option' => [],
            ],
        );

        $this->converter->apply(new Request(), $configuration);
    }
}

class TestUserRepository
{
    public function findByFullName($firstName, $lastName)
    {
        return $firstName.' '.$lastName;
    }
}
