<?php

namespace Symfony\Tests\Components\Validator;

use Symfony\Components\Validator\ValidationContext;
use Symfony\Components\Validator\Constraints\Min;
use Symfony\Components\Validator\Constraints\All;
use Symfony\Components\Validator\Constraints\AllValidator;

class AllValidatorTest extends \PHPUnit_Framework_TestCase
{
    protected $validator;
    protected $walker;
    protected $context;

    public function setUp()
    {
        $this->walker = $this->getMock('Symfony\Components\Validator\GraphWalker', array(), array(), '', false);
        $metadataFactory = $this->getMock('Symfony\Components\Validator\Mapping\ClassMetadataFactoryInterface');
        $messageInterpolator = $this->getMock('Symfony\Components\Validator\MessageInterpolator\MessageInterpolatorInterface');

        $this->context = new ValidationContext('Root', $this->walker, $metadataFactory, $messageInterpolator);

        $this->validator = new AllValidator();
        $this->validator->initialize($this->context);
    }

    public function testNullIsValid()
    {
        $this->assertTrue($this->validator->isValid(null, new All(new Min(4))));
    }

    public function testThrowsExceptionIfNotTraversable()
    {
        $this->setExpectedException('Symfony\Components\Validator\Exception\UnexpectedTypeException');

        $this->validator->isValid('foobar', new All(new Min(4)));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkSingleConstraint($array)
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('foo');

        $constraint = new Min(4);

        foreach ($array as $key => $value) {
            $this->walker->expects($this->once())
                                     ->method('walkConstraint')
                                     ->with($this->equalTo($constraint), $this->equalTo($value), $this->equalTo('MyGroup'), $this->equalTo('foo['.$key.']'));
        }

        $this->assertTrue($this->validator->isValid($array, new All($constraint)));
    }

    /**
     * @dataProvider getValidArguments
     */
    public function testWalkMultipleConstraints($array)
    {
        $this->context->setGroup('MyGroup');
        $this->context->setPropertyPath('foo');

        $constraint = new Min(4);
        // can only test for twice the same constraint because PHPUnits mocking
        // can't test method calls with different arguments
        $constraints = array($constraint, $constraint);

        foreach ($array as $key => $value) {
            $this->walker->expects($this->exactly(2))
                                     ->method('walkConstraint')
                                     ->with($this->equalTo($constraint), $this->equalTo($value), $this->equalTo('MyGroup'), $this->equalTo('foo['.$key.']'));
        }

        $this->assertTrue($this->validator->isValid($array, new All($constraints)));
    }

    public function getValidArguments()
    {
        return array(
            // can only test for one entry, because PHPUnits mocking does not allow
            // to expect multiple method calls with different arguments
            array(array(1)),
            array(new \ArrayObject(array(1))),
        );
    }
}