<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator;

require_once __DIR__.'/Fixtures/Entity.php';
require_once __DIR__.'/Fixtures/FailingConstraint.php';
require_once __DIR__.'/Fixtures/FailingConstraintValidator.php';
require_once __DIR__.'/Fixtures/FakeClassMetadataFactory.php';

use Symfony\Tests\Component\Validator\Fixtures\Entity;
use Symfony\Tests\Component\Validator\Fixtures\FakeClassMetadataFactory;
use Symfony\Tests\Component\Validator\Fixtures\FailingConstraint;
use Symfony\Component\Validator\Validator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintValidatorFactory;
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
            '',
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
            '',
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
            '',
            array(),
            $entity,
            'firstName',
            ''
        ));
        $violations->add(new ConstraintViolation(
            '',
            array(),
            $entity,
            'lastName',
            ''
        ));

        $result = $this->validator->validate($entity, array('First', 'Second'));

        $this->assertEquals($violations, $result);
    }

    public function testValidateProperty()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint());
        $this->factory->addClassMetadata($metadata);

        $result = $this->validator->validateProperty($entity, 'firstName');

        $this->assertEquals(1, count($result));
    }

    public function testValidatePropertyValue()
    {
        $entity = new Entity();
        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addPropertyConstraint('firstName', new FailingConstraint());
        $this->factory->addClassMetadata($metadata);

        $result = $this->validator->validatePropertyValue(get_class($entity), 'firstName', 'Bernhard');

        $this->assertEquals(1, count($result));
    }

    public function testValidateValue()
    {
        $result = $this->validator->validateValue('Bernhard', new FailingConstraint());

        $this->assertEquals(1, count($result));
    }

    public function testGetMetadataFactory()
    {
        $this->assertInstanceOf(
            'Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface',
            $this->validator->getMetadataFactory()
        );
    }
}
