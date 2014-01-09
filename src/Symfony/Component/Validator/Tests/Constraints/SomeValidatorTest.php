<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Some;
use Symfony\Component\Validator\Constraints\SomeValidator;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\ValidationVisitor;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Tests\Fixtures\EntityCollection;

/**
 * @author Marc Morera Merino <hyuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 *
 * @api
 */
class SomeValidatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var ExecutionContext
     *
     * Context mockup
     */
    protected $context;

    /**
     * @var SomeValidator
     *
     * Validator instance
     */
    protected $validator;

    /**
     * Set up method
     */
    protected function setUp()
    {

        $this->context = $this
            ->getMockBuilder('Symfony\Component\Validator\ExecutionContext')
            ->disableOriginalConstructor()
            ->setMethods(array())
            ->getMock();

        $this->validator = new SomeValidator();
        $this->validator->initialize($this->context);
    }

    /**
     * Tear down method
     */
    protected function tearDown()
    {
        $this->validator = null;
        $this->context = null;
    }

    /**
     * Tests that if null, just valid
     */
    public function testNullIsValid()
    {
        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(
            null,
            new Some(
                array(
                    'constraints' => array(
                        new Range(array('min' => 4))
                    ),
                    'min' => 1
                )
            )
        );
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->validate('foo.barbar', new Some(new Range(array('min' => 4))));
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\MissingOptionsException
     */
    public function testThrowsExceptionMinAndExactly()
    {
        $this->validator->validate(
            null,
            new Some(
                array(
                    'constraints' => array(
                        new Range(array('min' => 4))
                    ),
                    'min' => 1,
                    'exactly' => 2
                )
            )
        );
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\MissingOptionsException
     */
    public function testThrowsExceptionMaxAndExactly()
    {
        $this->validator->validate(
            null,
            new Some(
                array(
                    'constraints' => array(
                        new Range(array('min' => 4))
                    ),
                    'max' => 1,
                    'exactly' => 2
                )
            )
        );
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\MissingOptionsException
     */
    public function testThrowsExceptionMinGreatThanMax()
    {
        $this->validator->validate(
            null,
            new Some(
                array(
                    'constraints' => array(
                        new Range(array('min' => 4))
                    ),
                    'min' => 3,
                    'max' => 1
                )
            )
        );
    }

    /**
     * Testing when min and max are defined
     */
    public function testMinAndMax()
    {
        $this->validator->validate(
            null,
            new Some(
                array(
                    'constraints' => array(
                        new Range(array('min' => 4))
                    ),
                    'min'=>1,
                    'max'=>10,
                )
            )
        );
    }

    /**
     * Testing when min, max and exactly are defined
     *
     * @expectedException \Symfony\Component\Validator\Exception\MissingOptionsException
     */
    public function testMinAndMaxAndExactly()
    {
        $this->validator->validate(
            null,
            new Some(
                array(
                    'constraints' => array(
                        new Range(array('min' => 4))
                    ),
                    'min'=>1,
                    'max'=>10,
                    'exactly'=>10,
                )
            )
        );
    }

    /**
     * Testing when just max is defined
     */
    public function testMax()
    {
        $this->validator->validate(
            null,
            new Some(
                array(
                    'constraints' => array(
                        new Range(array('min' => 4))
                    ),
                    'max'=>10,
                )
            )
        );
    }

    /**
     * Validates success min
     *
     * @dataProvider getValidArguments
     */
    public function testSuccessMinValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(array('count'))
            ->getMock();

        $constraintViolationList
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(2));

        $this->context
            ->expects($this->once())
            ->method('getViolations')
            ->will($this->returnValue($constraintViolationList));

        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $constraint1 = new Range(array('min' => 2));
        $constraint2 = new Range(array('min' => 7));

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                array(
                    'constraints' => array(
                        $constraint1,
                        $constraint2,
                    ),
                    'min' => 3
                )
            ));
    }

    /**
     * Not validates success min
     *
     * @dataProvider getValidArguments
     */
    public function testNotSuccessMinValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(array('count'))
            ->getMock();

        $constraintViolationList
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(2));

        $this->context
            ->expects($this->once())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('getViolations')
            ->will($this->returnValue($constraintViolationList));

        $constraint1 = new Range(array('min' => 2));
        $constraint2 = new Range(array('min' => 7));

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                array(
                    'constraints' => array(
                        $constraint1,
                        $constraint2,
                    ),
                    'min' => 5
                )
            )
        );
    }

    /**
     * Validates success min
     *
     * @dataProvider getValidArguments
     */
    public function testSuccessMinMaxValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(array('count'))
            ->getMock();

        $constraintViolationList
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(2));

        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('getViolations')
            ->will($this->returnValue($constraintViolationList));

        $constraint1 = new Range(array('min' => 2));
        $constraint2 = new Range(array('min' => 7));

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                array(
                    'constraints' => array(
                        $constraint1,
                        $constraint2,
                    ),
                    'min' => 2,
                    'max' => 4,
                )
            )
        );
    }

    /**
     * Validates not success min
     *
     * @dataProvider getValidArguments
     */
    public function testNotSuccessMinMaxValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(array('count'))
            ->getMock();

        $constraintViolationList
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(2));

        $this->context
            ->expects($this->once())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('getViolations')
            ->will($this->returnValue($constraintViolationList));

        $constraint1 = new Range(array('min' => 2));
        $constraint2 = new Range(array('min' => 7));

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                array(
                    'constraints' => array(
                        $constraint1,
                        $constraint2,
                    ),
                    'min' => 1,
                    'max' => 3,
                )
            )
        );
    }

    /**
     * Validates success max
     *
     * @dataProvider getValidArguments
     */
    public function testSuccessMaxValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(array('count'))
            ->getMock();

        $constraintViolationList
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(2));

        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('getViolations')
            ->will($this->returnValue($constraintViolationList));

        $constraint1 = new Range(array('min' => 2));
        $constraint2 = new Range(array('min' => 7));

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                array(
                    'constraints' => array(
                        $constraint1,
                        $constraint2,
                    ),
                    'max' => 5,
                )
            )
        );
    }

    /**
     * Validates not success max
     *
     * @dataProvider getValidArguments
     */
    public function testNotSuccessMaxValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(array('count'))
            ->getMock();

        $constraintViolationList
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(2));

        $this->context
            ->expects($this->once())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('getViolations')
            ->will($this->returnValue($constraintViolationList));

        $constraint1 = new Range(array('min' => 2));
        $constraint2 = new Range(array('min' => 7));

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                array(
                    'constraints' => array(
                        $constraint1,
                        $constraint2,
                    ),
                    'max' => 2,
                )
            )
        );
    }

    /**
     * Validates success exactly
     *
     * @dataProvider getValidArguments
     */
    public function testSuccessExactlyValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(array('count'))
            ->getMock();

        $constraintViolationList
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(2));

        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('getViolations')
            ->will($this->returnValue($constraintViolationList));

        $constraint1 = new Range(array('min' => 2));
        $constraint2 = new Range(array('min' => 7));

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                array(
                    'constraints' => array(
                        $constraint1,
                        $constraint2,
                    ),
                    'exactly' => 4,
                )
            )
        );
    }

    /**
     * Validates not success exactly
     *
     * @dataProvider getValidArguments
     */
    public function testNotSuccessExactlyValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(array('count'))
            ->getMock();

        $constraintViolationList
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(2));

        $this->context
            ->expects($this->once())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('getViolations')
            ->will($this->returnValue($constraintViolationList));

        $constraint1 = new Range(array('min' => 2));
        $constraint2 = new Range(array('min' => 7));

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new Some(
                array(
                    'constraints' => array(
                        $constraint1,
                        $constraint2,
                    ),
                    'exactly' => 3,
                )
            )
        );
    }

    /**
     * Functional test, validating Some constraint
     *
     * Using exactly
     */
    public function testFunctionalSuccessExactly()
    {
        $metadataFactory = new FakeMetadataFactory();
        $visitor = new ValidationVisitor('Root', $metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\EntityCollection');
        $metadata->addPropertyConstraint('collection', new Some(
                array(
                    'constraints' => array(
                        new Range(array('min' => 2)),
                        new Range(array('min' => 3)),
                        new Range(array('min' => 4)),
                        new Range(array('min' => 5))
                    ),
                    'exactly' => 3,
                )
            )
        );
        $metadataFactory->addMetadata($metadata);

        $visitor->validate(new EntityCollection(), 'Default', '');
        $this->assertCount(0, $visitor->getViolations());
    }

    /**
     * Functional test, not validating Some constraint
     *
     * Using exactly
     */
    public function testFunctionalNotSuccessExactly()
    {
        $metadataFactory = new FakeMetadataFactory();
        $visitor = new ValidationVisitor('Root', $metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\EntityCollection');
        $metadata->addPropertyConstraint('collection', new Some(
                array(
                    'constraints' => array(
                        new Range(array('min' => 2)),
                        new Range(array('min' => 3)),
                        new Range(array('min' => 4)),
                        new Range(array('min' => 5))
                    ),
                    'exactly' => 1,
                )
            )
        );
        $metadataFactory->addMetadata($metadata);

        $visitor->validate(new EntityCollection(), 'Default', '');
        $this->assertCount(1, $visitor->getViolations());
    }

    /**
     * Adds validateValue assertions
     */
    protected function setValidateValueAssertions($array, $constraint1, $constraint2)
    {
        $iteration = 1;

        foreach ($array as $key => $value) {

            $this
                ->context
                ->expects($this->at($iteration++))
                ->method('validateValue')
                ->with($value, $constraint1, '['.$key.']');

            $this
                ->context
                ->expects($this->at($iteration++))
                ->method('validateValue')
                ->with($value, $constraint2, '['.$key.']');
        }
    }


    /**
     * Data provider
     */
    public function getValidArguments()
    {
        return array(
            array(array(5, 6, 7)),
            array(new \ArrayObject(array(5, 6, 7))),
        );
    }
}