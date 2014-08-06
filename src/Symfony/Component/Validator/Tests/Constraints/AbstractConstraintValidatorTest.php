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
use Symfony\Component\Validator\Context\LegacyExecutionContext;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Tests\Fixtures\StubGlobalExecutionContext;
use Symfony\Component\Validator\Validation;

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

        if (Validation::API_VERSION_2_4 === $this->getApiVersion()) {
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

        $validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');
        $contextualValidator = $this->getMock('Symfony\Component\Validator\Validator\ContextualValidatorInterface');

        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_5:
                $context = new ExecutionContext(
                    $validator,
                    $this->root,
                    $translator
                );
                break;
            case Validation::API_VERSION_2_5_BC:
                $context = new LegacyExecutionContext(
                    $validator,
                    $this->root,
                    $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface'),
                    $translator
                );
                break;
            default:
                throw new \RuntimeException('Invalid API version');
        }

        $context->setGroup($this->group);
        $context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);

        $validator->expects($this->any())
            ->method('inContext')
            ->with($context)
            ->will($this->returnValue($contextualValidator));

        return $context;
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

        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_4:
                $this->context = $this->createContext();
                $this->validator->initialize($this->context);
                break;
            case Validation::API_VERSION_2_5:
            case Validation::API_VERSION_2_5_BC:
                $this->context->setGroup($group);
                break;
        }
    }

    protected function setObject($object)
    {
        $this->object = $object;
        $this->metadata = is_object($object)
            ? new ClassMetadata(get_class($object))
            : null;

        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_4:
                $this->context = $this->createContext();
                $this->validator->initialize($this->context);
                break;
            case Validation::API_VERSION_2_5:
            case Validation::API_VERSION_2_5_BC:
                $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
                break;
        }
    }

    protected function setProperty($object, $property)
    {
        $this->object = $object;
        $this->metadata = is_object($object)
            ? new PropertyMetadata(get_class($object), $property)
            : null;

        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_4:
                $this->context = $this->createContext();
                $this->validator->initialize($this->context);
                break;
            case Validation::API_VERSION_2_5:
            case Validation::API_VERSION_2_5_BC:
                $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
                break;
        }
    }

    protected function setValue($value)
    {
        $this->value = $value;

        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_4:
                $this->context = $this->createContext();
                $this->validator->initialize($this->context);
                break;
            case Validation::API_VERSION_2_5:
            case Validation::API_VERSION_2_5_BC:
                $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
                break;
        }
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

        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_4:
                $this->context = $this->createContext();
                $this->validator->initialize($this->context);
                break;
            case Validation::API_VERSION_2_5:
            case Validation::API_VERSION_2_5_BC:
                $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
                break;
        }
    }

    protected function expectNoValidate()
    {
        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_4:
                $this->context->expects($this->never())
                    ->method('validate');
                $this->context->expects($this->never())
                    ->method('validateValue');
                break;
            case Validation::API_VERSION_2_5:
            case Validation::API_VERSION_2_5_BC:
                $validator = $this->context->getValidator()->inContext($this->context);
                $validator->expects($this->never())
                    ->method('atPath');
                $validator->expects($this->never())
                    ->method('validate');
                break;
        }
    }

    protected function expectValidateAt($i, $propertyPath, $value, $group)
    {
        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_4:
                $this->context->expects($this->at($i))
                    ->method('validate')
                    ->with($value, $propertyPath, $group);
                break;
            case Validation::API_VERSION_2_5:
            case Validation::API_VERSION_2_5_BC:
                $validator = $this->context->getValidator()->inContext($this->context);
                $validator->expects($this->at(2 * $i))
                    ->method('atPath')
                    ->with($propertyPath)
                    ->will($this->returnValue($validator));
                $validator->expects($this->at(2 * $i + 1))
                    ->method('validate')
                    ->with($value, $this->logicalOr(null, array()), $group);
                break;
        }
    }

    protected function expectValidateValueAt($i, $propertyPath, $value, $constraints, $group)
    {
        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_4:
                $this->context->expects($this->at($i))
                    ->method('validateValue')
                    ->with($value, $constraints, $propertyPath, $group);
                break;
            case Validation::API_VERSION_2_5:
            case Validation::API_VERSION_2_5_BC:
                $contextualValidator = $this->context->getValidator()->inContext($this->context);
                $contextualValidator->expects($this->at(2 * $i))
                    ->method('atPath')
                    ->with($propertyPath)
                    ->will($this->returnValue($contextualValidator));
                $contextualValidator->expects($this->at(2 * $i + 1))
                    ->method('validate')
                    ->with($value, $constraints, $group);
                break;
        }
    }

    protected function assertNoViolation()
    {
        $this->assertCount(0, $this->context->getViolations());
    }

    protected function assertViolation($message, array $parameters = array(), $propertyPath = 'property.path', $invalidValue = 'InvalidValue', $plural = null, $code = null)
    {
        $violations = $this->context->getViolations();

        $this->assertCount(1, $violations);
        $this->assertEquals($this->createViolation($message, $parameters, $propertyPath, $invalidValue, $plural, $code), current(iterator_to_array($violations)));
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

    abstract protected function getApiVersion();

    abstract protected function createValidator();
}
