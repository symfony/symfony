<?php

namespace Symfony\Tests\Component\Validator;

require_once __DIR__.'/Fixtures/Entity.php';
require_once __DIR__.'/Fixtures/ConstraintA.php';
require_once __DIR__.'/Fixtures/ConstraintAValidator.php';

use Symfony\Tests\Component\Validator\Fixtures\Entity;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintA;
use Symfony\Component\Validator\GraphWalker;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Any;
use Symfony\Component\Validator\Constraints\Valid;

class GraphWalkerTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Tests\Component\Validator\Fixtures\Entity';

    protected $interpolator;
    protected $factory;
    protected $metadata;

    public function setUp()
    {
        $this->interpolator = $this->getMock('Symfony\Component\Validator\MessageInterpolator\MessageInterpolatorInterface');
        $this->factory = $this->getMock('Symfony\Component\Validator\Mapping\ClassMetadataFactoryInterface');
        $this->walker = new GraphWalker('Root', $this->factory, new ConstraintValidatorFactory(), $this->interpolator);
        $this->metadata = new ClassMetadata(self::CLASSNAME);
    }

    public function testWalkClassValidatesConstraints()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $this->walker->walkClass($this->metadata, new Entity(), 'Default', '');

        $this->assertEquals(1, count($this->walker->getViolations()));
    }

    public function testWalkClassValidatesPropertyConstraints()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->walker->walkClass($this->metadata, new Entity(), 'Default', '');

        $this->assertEquals(1, count($this->walker->getViolations()));
    }

    public function testWalkClassValidatesGetterConstraints()
    {
        $this->metadata->addGetterConstraint('lastName', new ConstraintA());

        $this->walker->walkClass($this->metadata, new Entity(), 'Default', '');

        $this->assertEquals(1, count($this->walker->getViolations()));
    }

    public function testWalkPropertyValueValidatesConstraints()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->walker->walkPropertyValue($this->metadata, 'firstName', 'value', 'Default', '');

        $this->assertEquals(1, count($this->walker->getViolations()));
    }

    public function testWalkConstraintBuildsAViolationIfFailed()
    {
        $constraint = new ConstraintA();

        $this->interpolator->expects($this->once())
                                             ->method('interpolate')
                                             ->with($this->equalTo('message'), $this->equalTo(array('param' => 'value')))
                                             ->will($this->returnValue('interpolated text'));

        $this->walker->walkConstraint($constraint, 'foobar', 'Default', 'firstName.path');

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'interpolated text',
            'Root',
            'firstName.path',
            'foobar'
        ));

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkConstraintBuildsNoViolationIfSuccessful()
    {
        $constraint = new ConstraintA();

        $this->walker->walkConstraint($constraint, 'VALID', 'Default', 'firstName.path');

        $this->assertEquals(0, count($this->walker->getViolations()));
    }
}