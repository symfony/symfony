<?php

namespace Symfony\Tests\Components\Validator;

require_once __DIR__.'/Fixtures/Entity.php';
require_once __DIR__.'/Fixtures/ConstraintA.php';
require_once __DIR__.'/Fixtures/ConstraintAValidator.php';

use Symfony\Tests\Components\Validator\Fixtures\Entity;
use Symfony\Tests\Components\Validator\Fixtures\ConstraintA;
use Symfony\Components\Validator\GraphWalker;
use Symfony\Components\Validator\ConstraintViolation;
use Symfony\Components\Validator\ConstraintViolationList;
use Symfony\Components\Validator\ConstraintValidatorFactory;
use Symfony\Components\Validator\Mapping\ClassMetadata;
use Symfony\Components\Validator\Mapping\PropertyMetadata;
use Symfony\Components\Validator\Constraints\All;
use Symfony\Components\Validator\Constraints\Any;
use Symfony\Components\Validator\Constraints\Valid;

class GraphWalkerTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Tests\Components\Validator\Fixtures\Entity';

    protected $interpolator;
    protected $factory;
    protected $metadata;

    public function setUp()
    {
        $this->interpolator = $this->getMock('Symfony\Components\Validator\MessageInterpolator\MessageInterpolatorInterface');
        $this->factory = $this->getMock('Symfony\Components\Validator\Mapping\ClassMetadataFactoryInterface');
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