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
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\Tests\Fixtures\FailingConstraint;
use Symfony\Component\Validator\Validator;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class ValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FakeMetadataFactory
     */
    private $metadataFactory;

    /**
     * @var Validator
     */
    private $validator;

    protected function setUp()
    {
        $this->metadataFactory = new FakeMetadataFactory();
        $this->validator = new Validator($this->metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
    }

    protected function tearDown()
    {
        $this->metadataFactory = null;
        $this->validator = null;
    }

    public function testValidateDefaultGroup()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint());
        $metadata->addPropertyConstraint('lastName', new FailingConstraint(array(
            'groups' => 'Custom',
        )));
        $this->metadataFactory->addMetadata($metadata);

        // Only the constraint of group "Default" failed
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            $entity,
            'firstName',
            ''
        ));

        $this->assertEquals($violations, $this->validator->validate($entity));
    }

    public function testValidateOneGroup()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint());
        $metadata->addPropertyConstraint('lastName', new FailingConstraint(array(
            'groups' => 'Custom',
        )));
        $this->metadataFactory->addMetadata($metadata);

        // Only the constraint of group "Custom" failed
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            $entity,
            'lastName',
            ''
        ));

        $this->assertEquals($violations, $this->validator->validate($entity, 'Custom'));
    }

    public function testValidateMultipleGroups()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint(array(
            'groups' => 'First',
        )));
        $metadata->addPropertyConstraint('lastName', new FailingConstraint(array(
            'groups' => 'Second',
        )));
        $this->metadataFactory->addMetadata($metadata);

        // The constraints of both groups failed
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            $entity,
            'firstName',
            ''
        ));
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            $entity,
            'lastName',
            ''
        ));

        $result = $this->validator->validate($entity, array('First', 'Second'));

        $this->assertEquals($violations, $result);
    }

    public function testValidateGroupSequenceProvider()
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
        $this->metadataFactory->addMetadata($metadata);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
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
        $this->metadataFactory->addMetadata($metadata);

        $result = $this->validator->validateProperty($entity, 'firstName');

        $this->assertCount(1, $result);

        $result = $this->validator->validateProperty($entity, 'lastName');

        $this->assertCount(0, $result);
    }

    public function testValidatePropertyValue()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint());
        $this->metadataFactory->addMetadata($metadata);

        $result = $this->validator->validatePropertyValue(get_class($entity), 'firstName', 'Bernhard');

        $this->assertCount(1, $result);
    }

    public function testValidateValue()
    {
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            '',
            '',
            'Bernhard'
        ));

        $this->assertEquals($violations, $this->validator->validateValue('Bernhard', new FailingConstraint()));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testValidateValueRejectsValid()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $this->metadataFactory->addMetadata($metadata);

        $this->validator->validateValue($entity, new Valid());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testValidatePropertyFailsIfPropertiesNotSupported()
    {
        // $metadata does not implement PropertyMetadataContainerInterface
        $metadata = $this->getMock('Symfony\Component\Validator\MetadataInterface');
        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface');
        $this->metadataFactory->expects($this->any())
            ->method('getMetadataFor')
            ->with('VALUE')
            ->will($this->returnValue($metadata));
        $this->validator = new Validator($this->metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());

        $this->validator->validateProperty('VALUE', 'someProperty');
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     */
    public function testValidatePropertyValueFailsIfPropertiesNotSupported()
    {
        // $metadata does not implement PropertyMetadataContainerInterface
        $metadata = $this->getMock('Symfony\Component\Validator\MetadataInterface');
        $this->metadataFactory = $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface');
        $this->metadataFactory->expects($this->any())
            ->method('getMetadataFor')
            ->with('VALUE')
            ->will($this->returnValue($metadata));
        $this->validator = new Validator($this->metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());

        $this->validator->validatePropertyValue('VALUE', 'someProperty', 'propertyValue');
    }

    public function testGetMetadataFactory()
    {
        $this->assertInstanceOf(
            'Symfony\Component\Validator\MetadataFactoryInterface',
            $this->validator->getMetadataFactory()
        );
    }
}
