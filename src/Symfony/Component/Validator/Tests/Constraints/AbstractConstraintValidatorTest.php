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
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
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
                $this->propertyPath
            ))
            ->setMethods(array('validate', 'validateValue'))
            ->getMock();
    }

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

    protected function assertViolation($message, array $parameters = array(), $propertyPath = 'property.path', $invalidValue = 'InvalidValue', $plural = null, $code = null)
    {
        $violations = $this->context->getViolations();

        $this->assertCount(1, $violations);
        $this->assertEquals($this->createViolation($message, $parameters, $propertyPath, $invalidValue, $plural, $code), $violations[0]);
    }

    protected function assertViolations(array $expected)
    {
        $violations = $this->context->getViolations();

        $this->assertCount(count($expected), $violations);

        $i = 0;

        foreach ($expected as $violation) {
            $this->assertEquals($violation, $violations[$i++]);
        }
    }

    abstract protected function createValidator();
}
