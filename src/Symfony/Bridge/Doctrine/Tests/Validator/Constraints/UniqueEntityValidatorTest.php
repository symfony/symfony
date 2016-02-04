<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Validator\Constraints;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;
use Symfony\Bridge\Doctrine\Tests\Fixtures\ChildEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoubleNameEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\HighestEntity\InstanceEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Component\Validator\Tests\Constraints\AbstractConstraintValidatorTest;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class UniqueEntityValidatorTest extends AbstractConstraintValidatorTest
{
    const EM_NAME = 'foo';

    /**
     * @var ObjectManager
     */
    protected $em;

    /**
     * @var ManagerRegistry
     */
    protected $registry;

    /**
     * @var ObjectRepository
     */
    protected $repository;

    protected function setUp()
    {
        $this->em = DoctrineTestHelper::createTestEntityManager();
        $this->registry = $this->createRegistryMock($this->em);
        $this->createSchema($this->em);

        parent::setUp();
    }

    protected function createRegistryMock(ObjectManager $em = null)
    {
        $registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $registry->expects($this->any())
                 ->method('getManager')
                 ->with($this->equalTo(self::EM_NAME))
                 ->will($this->returnValue($em));

        return $registry;
    }

    protected function createRepositoryMock()
    {
        $repository = $this->getMockBuilder('Doctrine\Common\Persistence\ObjectRepository')
            ->setMethods(array('findByCustom', 'find', 'findAll', 'findOneBy', 'findBy', 'getClassName'))
            ->getMock()
        ;

        return $repository;
    }

    protected function createEntityManagerMock($repositoryMock)
    {
        $em = $this->getMockBuilder('Doctrine\Common\Persistence\ObjectManager')
            ->getMock()
        ;

        if ($repositoryMock) {
            $em->expects($this->any())
               ->method('getRepository')
               ->will($this->returnValue($repositoryMock))
            ;
        }

        $metadataFactory = $this->getMock('Doctrine\Common\Persistence\Mapping\ClassMetadataFactory');

        $metadata = [];

        $metadataFactory
            ->expects($this->any())
            ->method('getMetadataFor')
            ->will($this->returnCallback(function($className) use (&$metadata) {
                if (isset($metadata[$className])) {
                    return $metadata[$className];
                }

                $classMetadata = $this->getMock('Doctrine\Common\Persistence\Mapping\ClassMetadata');

                $classMetadata
                    ->expects($this->any())
                    ->method('getName')
                    ->will($this->returnValue($className))
                ;

                $classMetadata
                    ->expects($this->any())
                    ->method('hasField')
                    ->will($this->returnValue(true))
                ;
                $reflParser = $this->getMockBuilder('Doctrine\Common\Reflection\StaticReflectionParser')
                    ->disableOriginalConstructor()
                    ->getMock()
                ;
                $refl = $this->getMockBuilder('Doctrine\Common\Reflection\StaticReflectionProperty')
                    ->setConstructorArgs(array($reflParser, 'property-name'))
                    ->setMethods(array('getValue'))
                    ->getMock()
                ;
                $refl
                    ->expects($this->any())
                    ->method('getValue')
                    ->will($this->returnValue(true))
                ;
                $classMetadata->reflFields = array('name' => $refl);

                $classMetadata->isMappedSuperclass = false !== strpos($className, 'MappedSuperClass');

                return $metadata[$className] = $classMetadata;
            }))
        ;

        $metadataFactory
            ->expects($this->any())
            ->method('isTransient')
            ->will($this->returnCallback(function($className) {
                return false !== strpos($className, 'Transient');
            }))
        ;

        $em->expects($this->any())
           ->method('getMetadataFactory')
           ->will($this->returnValue($metadataFactory))
        ;

        $em->expects($this->any())
             ->method('getClassMetadata')
             ->will($this->returnCallback([$metadataFactory, 'getMetadataFor']))
        ;

        return $em;
    }

    protected function createValidator()
    {
        return new UniqueEntityValidator($this->registry);
    }

    private function createSchema(ObjectManager $em)
    {
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema(array(
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity'),
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\DoubleNameEntity'),
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIntIdEntity'),
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity'),
        ));
    }

    /**
     * This is a functional test as there is a large integration necessary to get the validator working.
     */
    public function testValidateUniqueness()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity',
        ));

        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Foo');

        $this->validator->validate($entity1, $constraint);

        $this->assertNoViolation();

        $this->em->persist($entity1);
        $this->em->flush();

        $this->validator->validate($entity1, $constraint);

        $this->assertNoViolation();

        $this->validator->validate($entity2, $constraint);

        $this->buildViolation('myMessage')
            ->atPath('property.path.name')
            ->setParameter('{{ value }}', 'Foo')
            ->setInvalidValue('Foo')
            ->assertRaised();
    }

    public function testValidateCustomErrorPath()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'errorPath' => 'bar',
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity',
        ));

        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Foo');

        $this->em->persist($entity1);
        $this->em->flush();

        $this->validator->validate($entity2, $constraint);

        $this->buildViolation('myMessage')
            ->atPath('property.path.bar')
            ->setParameter('{{ value }}', 'Foo')
            ->setInvalidValue('Foo')
            ->assertRaised();
    }

    public function testValidateUniquenessWithNull()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity',
        ));

        $entity1 = new SingleIntIdEntity(1, null);
        $entity2 = new SingleIntIdEntity(2, null);

        $this->em->persist($entity1);
        $this->em->persist($entity2);
        $this->em->flush();

        $this->validator->validate($entity1, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateUniquenessWithIgnoreNull()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name', 'name2'),
            'em' => self::EM_NAME,
            'ignoreNull' => false,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\DoubleNameEntity',
        ));

        $entity1 = new DoubleNameEntity(1, 'Foo', null);
        $entity2 = new DoubleNameEntity(2, 'Foo', null);

        $this->validator->validate($entity1, $constraint);

        $this->assertNoViolation();

        $this->em->persist($entity1);
        $this->em->flush();

        $this->validator->validate($entity1, $constraint);

        $this->assertNoViolation();

        $this->validator->validate($entity2, $constraint);

        $this->buildViolation('myMessage')
            ->atPath('property.path.name')
            ->setParameter('{{ value }}', 'Foo')
            ->setInvalidValue('Foo')
            ->assertRaised();
    }

    public function testValidateUniquenessWithValidCustomErrorPath()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name', 'name2'),
            'em' => self::EM_NAME,
            'errorPath' => 'name2',
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\DoubleNameEntity',
        ));

        $entity1 = new DoubleNameEntity(1, 'Foo', 'Bar');
        $entity2 = new DoubleNameEntity(2, 'Foo', 'Bar');

        $this->validator->validate($entity1, $constraint);

        $this->assertNoViolation();

        $this->em->persist($entity1);
        $this->em->flush();

        $this->validator->validate($entity1, $constraint);

        $this->assertNoViolation();

        $this->validator->validate($entity2, $constraint);

        $this->buildViolation('myMessage')
            ->atPath('property.path.name2')
            ->setParameter('{{ value }}', 'Bar')
            ->setInvalidValue('Bar')
            ->assertRaised();
    }

    public function testValidateUniquenessUsingCustomRepositoryMethod()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'repositoryMethod' => 'findByCustom',
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity',
        ));

        $repository = $this->createRepositoryMock();
        $repository->expects($this->once())
             ->method('findByCustom')
             ->will($this->returnValue(array()))
        ;
        $this->em = $this->createEntityManagerMock($repository);
        $this->registry = $this->createRegistryMock($this->em);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $entity1 = new SingleIntIdEntity(1, 'foo');

        $this->validator->validate($entity1, $constraint);

        $this->assertNoViolation();
    }

    public function testValidateUniquenessWithUnrewoundArray()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'repositoryMethod' => 'findByCustom',
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity',
        ));

        $entity = new SingleIntIdEntity(1, 'foo');

        $repository = $this->createRepositoryMock();
        $repository->expects($this->once())
            ->method('findByCustom')
            ->will(
                $this->returnCallback(function () use ($entity) {
                    $returnValue = array(
                        $entity,
                    );
                    next($returnValue);

                    return $returnValue;
                })
            )
        ;
        $this->em = $this->createEntityManagerMock($repository);
        $this->registry = $this->createRegistryMock($this->em);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate($entity, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider resultTypesProvider
     */
    public function testValidateResultTypes($entity1, $result)
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'repositoryMethod' => 'findByCustom',
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity',
        ));

        $repository = $this->createRepositoryMock();
        $repository->expects($this->once())
            ->method('findByCustom')
            ->will($this->returnValue($result))
        ;
        $this->em = $this->createEntityManagerMock($repository);
        $this->registry = $this->createRegistryMock($this->em);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate($entity1, $constraint);

        $this->assertNoViolation();
    }

    public function resultTypesProvider()
    {
        $entity = new SingleIntIdEntity(1, 'foo');

        return array(
            array($entity, array($entity)),
            array($entity, new \ArrayIterator(array($entity))),
            array($entity, new ArrayCollection(array($entity))),
        );
    }

    public function testAssociatedEntity()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('single'),
            'em' => self::EM_NAME,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity',
        ));

        $entity1 = new SingleIntIdEntity(1, 'foo');
        $associated = new AssociationEntity();
        $associated->single = $entity1;
        $associated2 = new AssociationEntity();
        $associated2->single = $entity1;

        $this->em->persist($entity1);
        $this->em->persist($associated);
        $this->em->flush();

        $this->validator->validate($associated, $constraint);

        $this->assertNoViolation();

        $this->em->persist($associated2);
        $this->em->flush();

        $this->validator->validate($associated2, $constraint);

        $this->buildViolation('myMessage')
            ->atPath('property.path.single')
            ->setParameter('{{ value }}', 1)
            ->setInvalidValue(1)
            ->assertRaised();
    }

    public function testAssociatedEntityWithNull()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('single'),
            'em' => self::EM_NAME,
            'ignoreNull' => false,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity',
        ));

        $associated = new AssociationEntity();
        $associated->single = null;

        $this->em->persist($associated);
        $this->em->flush();

        $this->validator->validate($associated, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage Associated entities are not allowed to have more than one identifier field
     */
    public function testAssociatedCompositeEntity()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('composite'),
            'em' => self::EM_NAME,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity',
        ));

        $composite = new CompositeIntIdEntity(1, 1, 'test');
        $associated = new AssociationEntity();
        $associated->composite = $composite;

        $this->em->persist($composite);
        $this->em->persist($associated);
        $this->em->flush();

        $this->validator->validate($associated, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage Object manager "foo" does not exist.
     */
    public function testDedicatedEntityManagerNullObject()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity',
        ));

        $this->em = null;
        $this->registry = $this->createRegistryMock($this->em);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $entity = new SingleIntIdEntity(1, null);

        $this->validator->validate($entity, $constraint);
    }

    public function testTargetsRepoIsUsedForChildren()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity',
        ));

        $repository = $this->createRepositoryMock();
        $this->em = $this->createEntityManagerMock(null);
        $this->em->expects($this->atLeastOnce())
             ->method('getRepository')
             ->with('Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity')
             ->will($this->returnValue($repository))
        ;
        $this->registry = $this->createRegistryMock($this->em);

        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $entity = new ChildEntity(1, 'Foo');

        $this->validator->validate($entity, $constraint);
    }

    public function testTargetRepoIsHighestEntityFromTransient()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\HighestEntity\TargetTransient',
        ));

        $repository = $this->createRepositoryMock();
        $this->em = $this->createEntityManagerMock(null);
        $this->em->expects($this->atLeastOnce())
             ->method('getRepository')
             ->with('Symfony\Bridge\Doctrine\Tests\Fixtures\HighestEntity\GoodEntity')
             ->will($this->returnValue($repository))
        ;
        $this->registry = $this->createRegistryMock($this->em);

        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $entity = new InstanceEntity(1, 'Foo');

        $this->validator->validate($entity, $constraint);
    }

    public function testTargetRepoIsHighestEntityFromMappedSuperClass()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
            'target' => 'Symfony\Bridge\Doctrine\Tests\Fixtures\HighestEntity\TargetMappedSuperClass',
        ));

        $repository = $this->createRepositoryMock();
        $this->em = $this->createEntityManagerMock(null);
        $this->em->expects($this->atLeastOnce())
             ->method('getRepository')
             ->with('Symfony\Bridge\Doctrine\Tests\Fixtures\HighestEntity\GoodEntity')
             ->will($this->returnValue($repository))
        ;
        $this->registry = $this->createRegistryMock($this->em);

        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $entity = new InstanceEntity(1, 'Foo');

        $this->validator->validate($entity, $constraint);
    }
}
