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

use Symfony\Component\Validator\Constraints\None;
use Symfony\Component\Validator\Constraints\NoneValidator;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ExecutionContext;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Tests\Fixtures\EntityCollection;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\ValidationVisitor;

/**
 * @author Marc Morera Merino <yuhu@mmoreram.com>
 * @author Marc Morales Valldepérez <marcmorales83@gmail.com>
 */
class NoneValidatorTest extends \PHPUnit\Framework\TestCase
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
     * Set up method.
     */
    protected function setUp()
    {
        $this->context = $this
            ->getMockBuilder('Symfony\Component\Validator\ExecutionContext')
            ->disableOriginalConstructor()
            ->setMethods([])
            ->getMock();

        $this->validator = new NoneValidator();
        $this->validator->initialize($this->context);
    }

    /**
     * Tear down method.
     */
    protected function tearDown()
    {
        $this->validator = null;
        $this->context = null;
    }

    /**
     * Tests that if null, just valid.
     */
    public function testNullIsValid()
    {
        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $this->validator->validate(
            null,
            new None(
                [
                    'constraints' => [
                        new Range(['min' => 10]),
                    ],
                ]
            )
        );
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnexpectedTypeException
     */
    public function testThrowsExceptionIfNotTraversable()
    {
        $this->validator->validate('foo.barbar', new None(new Range(['min' => 4])));
    }

    /**
     * Validates success.
     *
     * @dataProvider getValidArguments
     */
    public function testSuccessValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(['count'])
            ->getMock();

        $constraintViolationList
            ->expects($this->once())
            ->method('count')
            ->will($this->returnValue(6));

        $this->context
            ->expects($this->never())
            ->method('addViolation');

        $this->context
            ->expects($this->once())
            ->method('getViolations')
            ->will($this->returnValue($constraintViolationList));

        $constraint1 = new Range(['min' => 8]);
        $constraint2 = new Range(['min' => 9]);

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new None(
                [
                    'constraints' => [
                        $constraint1,
                        $constraint2,
                    ],
                ]
            )
        );
    }

    /**
     * Validates not success.
     *
     * @dataProvider getValidArguments
     */
    public function testNotSuccessValidate($array)
    {
        $constraintViolationList = $this
            ->getMockBuilder('Symfony\Component\Validator\ConstraintViolationList')
            ->disableOriginalConstructor()
            ->setMethods(['count'])
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

        $constraint1 = new Range(['min' => 2]);
        $constraint2 = new Range(['min' => 7]);

        $this->setValidateValueAssertions($array, $constraint1, $constraint2);

        $this->validator->validate(
            $array,
            new None(
                [
                    'constraints' => [
                        $constraint1,
                        $constraint2,
                    ],
                ]
            )
        );
    }

    /**
     * Adds validateValue assertions.
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
     * Functional test, validating None constraint.
     *
     * Using exactly
     */
    public function testFunctionalSuccessExactly()
    {
        $metadataFactory = new FakeMetadataFactory();
        $visitor = new ValidationVisitor('Root', $metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\EntityCollection');
        $metadata->addPropertyConstraint('collection', new None(
                [
                    'constraints' => [
                        new Range(['min' => 4]),
                        new Range(['min' => 5]),
                        new Range(['min' => 6]),
                    ],
                ]
            )
        );
        $metadataFactory->addMetadata($metadata);
        $visitor->validate(new EntityCollection(), 'Default', '');
        $this->assertCount(0, $visitor->getViolations());
    }

    /**
     * Functional test, not validating None constraint.
     *
     * Using exactly
     */
    public function testFunctionalNotSuccessExactly()
    {
        $metadataFactory = new FakeMetadataFactory();
        $visitor = new ValidationVisitor('Root', $metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\EntityCollection');
        $metadata->addPropertyConstraint('collection', new None(
                [
                    'constraints' => [
                        new Range(['min' => 1]),
                        new Range(['min' => 2]),
                        new Range(['min' => 3]),
                    ],
                ]
            )
        );
        $metadataFactory->addMetadata($metadata);
        $visitor->validate(new EntityCollection(), 'Default', '');
        $this->assertCount(1, $visitor->getViolations());
    }

    /**
     * Data provider.
     */
    public function getValidArguments()
    {
        return [
            [[5, 6, 7]],
            [new \ArrayObject([5, 6, 7])],
        ];
    }
}
