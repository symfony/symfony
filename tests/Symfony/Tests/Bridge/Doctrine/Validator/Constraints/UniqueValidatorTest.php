<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Bridge\Doctrine\Validator\Constraints;

require_once __DIR__.'/../../DoctrineOrmTestCase.php';
require_once __DIR__.'/../../Fixtures/SingleIdentEntity.php';
require_once __DIR__.'/../../Fixtures/CompositeIdentEntity.php';
require_once __DIR__.'/../../Fixtures/AssociationEntity.php';

use Symfony\Tests\Bridge\Doctrine\DoctrineOrmTestCase;
use Symfony\Tests\Bridge\Doctrine\Fixtures\SingleIdentEntity;
use Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeIdentEntity;
use Symfony\Tests\Bridge\Doctrine\Fixtures\AssociationEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntityValidator;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator;
use Doctrine\ORM\Tools\SchemaTool;

class UniqueValidatorTest extends DoctrineOrmTestCase
{
    protected function createRegistryMock($entityManagerName, $em)
    {
        $registry = $this->getMock('Symfony\Bridge\Doctrine\RegistryInterface');
        $registry->expects($this->any())
                 ->method('getEntityManager')
                 ->with($this->equalTo($entityManagerName))
                 ->will($this->returnValue($em));

        return $registry;
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

    public function createValidator($entityManagerName, $em, $validateClass = null, $uniqueFields = null)
    {
        if (!$validateClass) {
            $validateClass = 'Symfony\Tests\Bridge\Doctrine\Fixtures\SingleIdentEntity';
        }
        if (!$uniqueFields) {
            $uniqueFields = array('name');
        }

        $registry = $this->createRegistryMock($entityManagerName, $em);

        $uniqueValidator = new UniqueEntityValidator($registry);

        $metadata = new ClassMetadata($validateClass);
        $metadata->addConstraint(new UniqueEntity(array('fields' => $uniqueFields, 'em' => $entityManagerName)));

        $metadataFactory = $this->createMetadataFactoryMock($metadata);
        $validatorFactory = $this->createValidatorFactory($uniqueValidator);

        return new Validator($metadataFactory, $validatorFactory);
    }

    private function createSchema($em)
    {
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema(array(
            $em->getClassMetadata('Symfony\Tests\Bridge\Doctrine\Fixtures\SingleIdentEntity'),
            $em->getClassMetadata('Symfony\Tests\Bridge\Doctrine\Fixtures\CompositeIdentEntity'),
            $em->getClassMetadata('Symfony\Tests\Bridge\Doctrine\Fixtures\AssociationEntity'),
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
        $this->assertEquals('This value is already used', $violation->getMessage());
        $this->assertEquals('name', $violation->getPropertyPath());
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

    /**
     * @group GH-1635
     */
    public function testAssociatedEntity()
    {
        $entityManagerName = "foo";
        $em = $this->createTestEntityManager();
        $this->createSchema($em);
        $validator = $this->createValidator($entityManagerName, $em, 'Symfony\Tests\Bridge\Doctrine\Fixtures\AssociationEntity', array('single'));

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
        $validator = $this->createValidator($entityManagerName, $em, 'Symfony\Tests\Bridge\Doctrine\Fixtures\AssociationEntity', array('composite'));

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
