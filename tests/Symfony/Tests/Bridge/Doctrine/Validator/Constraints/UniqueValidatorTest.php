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

require_once __DIR__.'/../../Form/DoctrineOrmTestCase.php';
require_once __DIR__.'/../../Fixtures/SingleIdentEntity.php';

use Symfony\Tests\Bridge\Doctrine\Form\DoctrineOrmTestCase;
use Symfony\Tests\Bridge\Doctrine\Form\Fixtures\SingleIdentEntity;
use Symfony\Bridge\Doctrine\Form\ChoiceList\EntityChoiceList;
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

    public function createValidator($entityManagerName, $em)
    {
        $registry = $this->createRegistryMock($entityManagerName, $em);

        $uniqueValidator = new UniqueEntityValidator($registry);

        $metadata = new ClassMetadata('Symfony\Tests\Bridge\Doctrine\Form\Fixtures\SingleIdentEntity');
        $metadata->addConstraint(new UniqueEntity(array('fields' => array('name'), 'em' => $entityManagerName)));

        $metadataFactory = $this->createMetadataFactoryMock($metadata);
        $validatorFactory = $this->createValidatorFactory($uniqueValidator);

        return new Validator($metadataFactory, $validatorFactory);
    }

    private function createSchema($em)
    {
        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema(array(
            $em->getClassMetadata('Symfony\Tests\Bridge\Doctrine\Form\Fixtures\SingleIdentEntity')
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
}
