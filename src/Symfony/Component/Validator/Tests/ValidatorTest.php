<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity;
use Symfony\Component\Validator\Tests\Fixtures\FakeClassMetadataFactory;
use Symfony\Component\Validator\Tests\Fixtures\FailingConstraint;
use Symfony\Component\Validator\Validator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $factory;
    protected $validator;

    protected function setUp()
    {
        $this->factory = new FakeClassMetadataFactory();
        $this->validator = new Validator($this->factory, new ConstraintValidatorFactory());
    }

    protected function tearDown()
    {
        $this->factory = null;
        $this->validator = null;
    }

    public function testValidate_defaultGroup()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint());
        $metadata->addPropertyConstraint('lastName', new FailingConstraint(array(
            'groups' => 'Custom',
        )));
        $this->factory->addClassMetadata($metadata);

        // Only the constraint of group "Default" failed
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            array(),
            $entity,
            'firstName',
            ''
        ));

        $this->assertEquals($violations, $this->validator->validate($entity));
    }

    public function testValidate_oneGroup()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint());
        $metadata->addPropertyConstraint('lastName', new FailingConstraint(array(
            'groups' => 'Custom',
        )));
        $this->factory->addClassMetadata($metadata);

        // Only the constraint of group "Custom" failed
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            array(),
            $entity,
            'lastName',
            ''
        ));

        $this->assertEquals($violations, $this->validator->validate($entity, 'Custom'));
    }

    public function testValidate_multipleGroups()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint(array(
            'groups' => 'First',
        )));
        $metadata->addPropertyConstraint('lastName', new FailingConstraint(array(
            'groups' => 'Second',
        )));
        $this->factory->addClassMetadata($metadata);

        // The constraints of both groups failed
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            array(),
            $entity,
            'firstName',
            ''
        ));
        $violations->add(new ConstraintViolation(
            'Failed',
            array(),
            $entity,
            'lastName',
            ''
        ));

        $result = $this->validator->validate($entity, array('First', 'Second'));

        $this->assertEquals($violations, $result);
    }

    public function testValidate_groupSequenceProvider()
    {
        $entity = new GroupSequenceProviderEntity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint(array(
            'groups' => 'First',
        )));
        $metadata->addPropertyConstraint('lastName', new FailingConstraint(array(
            'groups' => 'Second',
        )));
        $metadata->setGroupSequenceProvider(true);
        $this->factory->addClassMetadata($metadata);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            array(),
            $entity,
            'firstName',
            ''
        ));

        $entity->setGroups(array('First'));
        $result = $this->validator->validate($entity);
        $this->assertEquals($violations, $result);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            array(),
            $entity,
            'lastName',
            ''
        ));

        $entity->setGroups(array('Second'));
        $result = $this->validator->validate($entity);
        $this->assertEquals($violations, $result);

        $entity->setGroups(array());
        $result = $this->validator->validate($entity);
        $this->assertEquals(new ConstraintViolationList(), $result);
    }

    public function testValidateProperty()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint());
        $this->factory->addClassMetadata($metadata);

        $result = $this->validator->validateProperty($entity, 'firstName');

        $this->assertCount(1, $result);
    }

    public function testValidatePropertyValue()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint());
        $this->factory->addClassMetadata($metadata);

        $result = $this->validator->validatePropertyValue(get_class($entity), 'firstName', 'Bernhard');

        $this->assertCount(1, $result);
    }

    public function testValidateValue()
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            array(),
            '',
            '',
            'Bernhard'
        ));

        $this->assertEquals($violations, $this->validator->validateValue('Bernhard', new FailingConstraint()));
    }

    /**
     * @expectedException Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testValidateValueRejectsValid()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $this->factory->addClassMetadata($metadata);

        $this->validator->validateValue($entity, new Valid());
    }

    public function testGetMetadataFactory()
    {
        $this->assertInstanceOf(
            'Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface',
            $this->validator->getMetadataFactory()
        );
    }
}
