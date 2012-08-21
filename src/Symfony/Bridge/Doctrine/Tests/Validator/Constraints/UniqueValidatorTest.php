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

use Symfony\Bridge\Doctrine\Tests\DoctrineOrmTestCase;
use Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\DoubleIdentEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIdentEntity;
use Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator;
use Doctrine\ORM\Tools\SchemaTool;

class UniqueValidatorTest extends DoctrineOrmTestCase
{
    protected function setUp()
    {
        parent::setUp();

        if (!class_exists('Symfony\Component\Security\Core\SecurityContext')) {
            $this->markTestSkipped('The "Security" component is not available');
        }

        if (!class_exists('Symfony\Component\Validator\Constraint')) {
            $this->markTestSkipped('The "Validator" component is not available');
        }
    }

    protected function createRegistryMock($entityManagerName, $em)
    {
        $registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $registry->expects($this->any())
                 ->method('getManager')
                 ->with($this->equalTo($entityManagerName))
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
        $refl = $this->getMockBuilder('Doctrine\Common\Reflection\StaticReflectionProperty')
            ->disableOriginalConstructor()
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

    protected function createMetadataFactoryMock($metadata)
    {
        $metadataFactory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $metadataFactory->expects($this->any())
                        ->method('getClassMetadata')
                        ->with($this->equalTo($metadata->name))
                        ->will($this->returnValue($metadata));

        return $metadataFactory;
    }

    protected function createValidatorFactory($uniqueValidator)
    {
        $validatorFactory = $this->getMock('Symfony\Component\Validator\ConstraintValidatorFactoryInterface');
        $validatorFactory->expects($this->any())
                         ->method('getInstance')
                         ->with($this->isInstanceOf('Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity'))
                         ->will($this->returnValue($uniqueValidator));

        return $validatorFactory;
    }

    public function createValidator($entityManagerName, $em, $validateClass = null, $uniqueFields = null, $errorPath = null, $repositoryMethod = 'findBy', $ignoreNull = true)
    {
        if (!$validateClass) {
            $validateClass = 'Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity';
        }
        if (!$uniqueFields) {
            $uniqueFields = array('name');
        }

        $registry = $this->createRegistryMock($entityManagerName, $em);

        $uniqueValidator = new UniqueEntityValidator($registry);

        $metadata = new ClassMetadata($validateClass);
        $constraint = new UniqueEntity(array(
            'fields' => $uniqueFields,
            'em' => $entityManagerName,
            'errorPath' => $errorPath,
            'repositoryMethod' => $repositoryMethod,
            'ignoreNull' => $ignoreNull
        ));
        $metadata->addConstraint($constraint);

        $metadataFactory = $this->createMetadataFactoryMock($metadata);
        $validatorFactory = $this->createValidatorFactory($uniqueValidator);

        return new Validator($metadataFactory, $validatorFactory);
    }

    private function createSchema($em)
    {
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema(array(
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\SingleIdentEntity'),
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\DoubleIdentEntity'),
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\CompositeIdentEntity'),
            $em->getClassMetadata('Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity'),
        ));
    }

    /**
     * This is a functional test as there is a large integration necessary to get the validator working.
     */
    public function testValidateUniqueness()
    {
        $entityManagerName = "foo";
        $em = $this->createTestEntityManager();
        $this->createSchema($em);
        $validator = $this->createValidator($entityManagerName, $em);

        $entity1 = new SingleIdentEntity(1, 'Foo');
        $violationsList = $validator->validate($entity1);
        $this->assertEquals(0, $violationsList->count(), "No violations found on entity before it is saved to the database.");

        $em->persist($entity1);
        $em->flush();

        $violationsList = $validator->validate($entity1);
        $this->assertEquals(0, $violationsList->count(), "No violations found on entity after it was saved to the database.");

        $entity2 = new SingleIdentEntity(2, 'Foo');

        $violationsList = $validator->validate($entity2);
        $this->assertEquals(1, $violationsList->count(), "No violations found on entity after it was saved to the database.");

        $violation = $violationsList[0];
        $this->assertEquals('This value is already used.', $violation->getMessage());
        $this->assertEquals('name', $violation->getPropertyPath());
        $this->assertEquals('Foo', $violation->getInvalidValue());
    }

    public function testValidateCustomErrorPath()
    {
        $entityManagerName = "foo";
        $em = $this->createTestEntityManager();
        $this->createSchema($em);
        $validator = $this->createValidator($entityManagerName, $em, null, null, 'bar');

        $entity1 = new SingleIdentEntity(1, 'Foo');

        $em->persist($entity1);
        $em->flush();

        $entity2 = new SingleIdentEntity(2, 'Foo');

        $violationsList = $validator->validate($entity2);
        $this->assertEquals(1, $violationsList->count(), "No violations found on entity after it was saved to the database.");

        $violation = $violationsList[0];
        $this->assertEquals('This value is already used.', $violation->getMessage());
        $this->assertEquals('bar', $violation->getPropertyPath());
        $this->assertEquals('Foo', $violation->getInvalidValue());
    }

    public function testValidateUniquenessWithNull()
    {
        $entityManagerName = "foo";
        $em = $this->createTestEntityManager();
        $this->createSchema($em);
        $validator = $this->createValidator($entityManagerName, $em);

        $entity1 = new SingleIdentEntity(1, null);
        $entity2 = new SingleIdentEntity(2, null);

        $em->persist($entity1);
        $em->persist($entity2);
        $em->flush();

        $violationsList = $validator->validate($entity1);
        $this->assertEquals(0, $violationsList->count(), "No violations found on entity having a null value.");
    }

    public function testValidateUniquenessWithIgnoreNull()
    {
        $entityManagerName = "foo";
        $validateClass = 'Symfony\Bridge\Doctrine\Tests\Fixtures\DoubleIdentEntity';
        $em = $this->createTestEntityManager();
        $this->createSchema($em);
        $validator = $this->createValidator($entityManagerName, $em, $validateClass, array('name', 'name2'), 'bar', 'findby', false);

        $entity1 = new DoubleIdentEntity(1, 'Foo', null);
        $violationsList = $validator->validate($entity1);
        $this->assertEquals(0, $violationsList->count(), "No violations found on entity before it is saved to the database.");

        $em->persist($entity1);
        $em->flush();

        $violationsList = $validator->validate($entity1);
        $this->assertEquals(0, $violationsList->count(), "No violations found on entity after it was saved to the database.");

        $entity2 = new DoubleIdentEntity(2, 'Foo', null);

        $violationsList = $validator->validate($entity2);
        $this->assertEquals(1, $violationsList->count(), "Violation found on entity with conflicting entity existing in the database.");

        $violation = $violationsList[0];
        $this->assertEquals('This value is already used.', $violation->getMessage());
        $this->assertEquals('bar', $violation->getPropertyPath());
        $this->assertEquals('Foo', $violation->getInvalidValue());
    }

    public function testValidateUniquenessAfterConsideringMultipleQueryResults()
    {
        $entityManagerName = "foo";
        $em = $this->createTestEntityManager();
        $this->createSchema($em);
        $validator = $this->createValidator($entityManagerName, $em);

        $entity1 = new SingleIdentEntity(1, 'foo');
        $entity2 = new SingleIdentEntity(2, 'foo');

        $em->persist($entity1);
        $em->persist($entity2);
        $em->flush();

        $violationsList = $validator->validate($entity1);
        $this->assertEquals(1, $violationsList->count(), 'Violation found on entity with conflicting entity existing in the database.');

        $violationsList = $validator->validate($entity2);
        $this->assertEquals(1, $violationsList->count(), 'Violation found on entity with conflicting entity existing in the database.');
    }

    public function testValidateUniquenessUsingCustomRepositoryMethod()
    {
        $entityManagerName = 'foo';
        $repository = $this->createRepositoryMock();
        $repository->expects($this->once())
             ->method('findByCustom')
             ->will($this->returnValue(array()))
        ;
        $em = $this->createEntityManagerMock($repository);
        $validator = $this->createValidator($entityManagerName, $em, null, array(), null, 'findByCustom');

        $entity1 = new SingleIdentEntity(1, 'foo');

        $violationsList = $validator->validate($entity1);
        $this->assertEquals(0, $violationsList->count(), 'Violation is using custom repository method.');
    }

    /**
     * @group GH-1635
     */
    public function testAssociatedEntity()
    {
        $entityManagerName = "foo";
        $em = $this->createTestEntityManager();
        $this->createSchema($em);
        $validator = $this->createValidator($entityManagerName, $em, 'Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity', array('single'));

        $entity1 = new SingleIdentEntity(1, 'foo');
        $associated = new AssociationEntity();
        $associated->single = $entity1;

        $em->persist($entity1);
        $em->persist($associated);
        $em->flush();

        $violationsList = $validator->validate($associated);
        $this->assertEquals(0, $violationsList->count());

        $associated2 = new AssociationEntity();
        $associated2->single = $entity1;

        $em->persist($associated2);
        $em->flush();

        $violationsList = $validator->validate($associated2);
        $this->assertEquals(1, $violationsList->count());
    }

    /**
     * @group GH-1635
     */
    public function testAssociatedCompositeEntity()
    {
        $entityManagerName = "foo";
        $em = $this->createTestEntityManager();
        $this->createSchema($em);
        $validator = $this->createValidator($entityManagerName, $em, 'Symfony\Bridge\Doctrine\Tests\Fixtures\AssociationEntity', array('composite'));

        $composite = new CompositeIdentEntity(1, 1, "test");
        $associated = new AssociationEntity();
        $associated->composite = $composite;

        $em->persist($composite);
        $em->persist($associated);
        $em->flush();

        $this->setExpectedException(
            'Symfony\Component\Validator\Exception\ConstraintDefinitionException',
            'Associated entities are not allowed to have more than one identifier field'
        );
        $violationsList = $validator->validate($associated);
    }
}
