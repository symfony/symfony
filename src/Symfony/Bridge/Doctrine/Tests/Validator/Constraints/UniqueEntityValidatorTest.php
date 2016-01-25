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
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoubleNameEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity;
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
        $em->expects($this->any())
             ->method('getRepository')
             ->will($this->returnValue($repositoryMock))
        ;

        $classMetadata = $this->getMock('Doctrine\Common\Persistence\Mapping\ClassMetadata');
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
        $em->expects($this->any())
             ->method('getClassMetadata')
             ->will($this->returnValue($classMetadata))
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
        ));

        $entity1 = new SingleIntIdEntity(1, 'Foo');
        $entity2 = new SingleIntIdEntity(2, 'Foo');

        $this->em->persist($entity1);
        $this->em->flush();

        $this->validator->validate($entity2, $constraint);

        $this->buildViolation('myMessage')
            ->atPath('property.path.bar')
            ->setInvalidValue('Foo')
            ->assertRaised();
    }

    public function testValidateUniquenessWithNull()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
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
            ->setInvalidValue($entity1)
            ->assertRaised();
    }

    public function testAssociatedEntityWithNull()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('single'),
            'em' => self::EM_NAME,
            'ignoreNull' => false,
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
     * @expectedExceptionMessage Object manager "foo" does not exist.
     */
    public function testDedicatedEntityManagerNullObject()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            'em' => self::EM_NAME,
        ));

        $this->em = null;
        $this->registry = $this->createRegistryMock($this->em);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $entity = new SingleIntIdEntity(1, null);

        $this->validator->validate($entity, $constraint);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     * @expectedExceptionMessage Unable to find the object manager associated with an entity of class "Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIntIdEntity"
     */
    public function testEntityManagerNullObject()
    {
        $constraint = new UniqueEntity(array(
            'message' => 'myMessage',
            'fields' => array('name'),
            // no "em" option set
        ));

        $this->em = null;
        $this->registry = $this->createRegistryMock($this->em);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $entity = new SingleIntIdEntity(1, null);

        $this->validator->validate($entity, $constraint);
    }
}
