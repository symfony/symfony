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

use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Tests\Fixtures\StubGlobalExecutionContext;

/**
 * @since  2.5.3
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractConstraintValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ExecutionContextInterface
     */
    protected $context;

    /**
     * @var ConstraintValidatorInterface
     */
    protected $validator;

    protected $group;

    protected $metadata;

    protected $object;

    protected $value;

    protected $root;

    protected $propertyPath;

    protected function setUp()
    {
        $this->group = 'MyGroup';
        $this->metadata = null;
        $this->object = null;
        $this->value = 'InvalidValue';
        $this->root = 'root';
        $this->propertyPath = 'property.path';
        $this->context = $this->createContext();
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        \Locale::setDefault('en');
    }

    protected function createContext()
    {
        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');

        return $this->getMockBuilder('Symfony\Component\Validator\ExecutionContext')
            ->setConstructorArgs(array(
                new StubGlobalExecutionContext($this->root),
                $translator,
                null,
                $this->metadata,
                $this->value,
                $this->group,
                $this->propertyPath,
            ))
            ->setMethods(array('validate', 'validateValue'))
            ->getMock();
    }

    /**
     * @param        $message
     * @param array  $parameters
     * @param string $propertyPath
     * @param string $invalidValue
     * @param null   $plural
     * @param null   $code
     *
     * @return ConstraintViolation
     *
     * @deprecated To be removed in Symfony 3.0. Use
     *             {@link buildViolation()} instead.
     */
    protected function createViolation($message, array $parameters = array(), $propertyPath = 'property.path', $invalidValue = 'InvalidValue', $plural = null, $code = null)
    {
        return new ConstraintViolation(
            null,
            $message,
            $parameters,
            $this->root,
            $propertyPath,
            $invalidValue,
            $plural,
            $code
        );
    }

    protected function setGroup($group)
    {
        $this->group = $group;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function setObject($object)
    {
        $this->object = $object;
        $this->metadata = is_object($object)
            ? new ClassMetadata(get_class($object))
            : null;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function setProperty($object, $property)
    {
        $this->object = $object;
        $this->metadata = is_object($object)
            ? new PropertyMetadata(get_class($object), $property)
            : null;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function setValue($value)
    {
        $this->value = $value;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function setRoot($root)
    {
        $this->root = $root;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function setPropertyPath($propertyPath)
    {
        $this->propertyPath = $propertyPath;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function expectNoValidate()
    {
        $this->context->expects($this->never())
            ->method('validate');
        $this->context->expects($this->never())
            ->method('validateValue');
    }

    protected function expectValidateAt($i, $propertyPath, $value, $group)
    {
        $this->context->expects($this->at($i))
            ->method('validate')
            ->with($value, $propertyPath, $group);
    }

    protected function expectValidateValueAt($i, $propertyPath, $value, $constraints, $group)
    {
        $this->context->expects($this->at($i))
            ->method('validateValue')
            ->with($value, $constraints, $propertyPath, $group);
    }

    protected function assertNoViolation()
    {
        $this->assertCount(0, $this->context->getViolations());
    }

    /**
     * @param        $message
     * @param array  $parameters
     * @param string $propertyPath
     * @param string $invalidValue
     * @param null   $plural
     * @param null   $code
     *
     * @deprecated To be removed in Symfony 3.0. Use
     *             {@link buildViolation()} instead.
     */
    protected function assertViolation($message, array $parameters = array(), $propertyPath = 'property.path', $invalidValue = 'InvalidValue', $plural = null, $code = null)
    {
        $this->buildViolation($message)
            ->setParameters($parameters)
            ->atPath($propertyPath)
            ->setInvalidValue($invalidValue)
            ->setCode($code)
            ->setPlural($plural)
            ->assertRaised();
    }

    /**
     * @param array $expected
     *
     * @deprecated To be removed in Symfony 3.0. Use
     *             {@link buildViolation()} instead.
     */
    protected function assertViolations(array $expected)
    {
        $violations = $this->context->getViolations();

        $this->assertCount(count($expected), $violations);

        $i = 0;

        foreach ($expected as $violation) {
            $this->assertEquals($violation, $violations[$i++]);
        }
    }

    /**
     * @param $message
     *
     * @return ConstraintViolationAssertion
     */
    protected function buildViolation($message)
    {
        return new ConstraintViolationAssertion($this->context, $message);
    }

    abstract protected function createValidator();
}

/**
 * @internal
 */
class ConstraintViolationAssertion
{
    /**
     * @var ExecutionContextInterface
     */
    private $context;

    /**
     * @var ConstraintViolationAssertion[]
     */
    private $assertions;

    private $message;
    private $parameters = array();
    private $invalidValue = 'InvalidValue';
    private $propertyPath = 'property.path';
    private $translationDomain;
    private $plural;
    private $code;

    public function __construct(ExecutionContextInterface $context, $message, array $assertions = array())
    {
        $this->context = $context;
        $this->message = $message;
        $this->assertions = $assertions;
    }

    public function atPath($path)
    {
        $this->propertyPath = $path;

        return $this;
    }

    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function setTranslationDomain($translationDomain)
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    public function setInvalidValue($invalidValue)
    {
        $this->invalidValue = $invalidValue;

        return $this;
    }

    public function setPlural($number)
    {
        $this->plural = $number;

        return $this;
    }

    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    public function buildNextViolation($message)
    {
        $assertions = $this->assertions;
        $assertions[] = $this;

        return new self($this->context, $message, $assertions);
    }

    public function assertRaised()
    {
        $expected = array();
        foreach ($this->assertions as $assertion) {
            $expected[] = $assertion->getViolation();
        }
        $expected[] = $this->getViolation();

        $violations = iterator_to_array($this->context->getViolations());

        \PHPUnit_Framework_Assert::assertCount(count($expected), $violations);

        reset($violations);

        foreach ($expected as $violation) {
            \PHPUnit_Framework_Assert::assertEquals($violation, current($violations));
            next($violations);
        }
    }

    private function getViolation()
    {
        return new ConstraintViolation(
            null,
            $this->message,
            $this->parameters,
            $this->context->getRoot(),
            $this->propertyPath,
            $this->invalidValue,
            $this->plural,
            $this->code
        );
    }
}
