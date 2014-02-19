<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Validator;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\Reference;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Verifies that a validator satisfies the API of Symfony 2.5+.
 *
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Abstract2Dot5ApiTest extends AbstractValidatorTest
{
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * @param MetadataFactoryInterface $metadataFactory
     *
     * @return ValidatorInterface
     */
    abstract protected function createValidator(MetadataFactoryInterface $metadataFactory);

    protected function setUp()
    {
        parent::setUp();

        $this->validator = $this->createValidator($this->metadataFactory);
    }

    protected function validate($value, $constraints, $groups = null)
    {
        return $this->validator->validate($value, $constraints, $groups);
    }

    protected function validateObject($object, $groups = null)
    {
        return $this->validator->validateObject($object, $groups);
    }

    protected function validateObjects($objects, $groups = null, $deep = false)
    {
        return $this->validator->validateObjects($objects, $groups, $deep);
    }

    protected function validateProperty($object, $propertyName, $groups = null)
    {
        return $this->validator->validateProperty($object, $propertyName, $groups);
    }

    protected function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        return $this->validator->validatePropertyValue($object, $propertyName, $value, $groups);
    }

    public function testNoDuplicateValidationIfConstraintInMultipleGroups()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => array('Group 1', 'Group 2'),
        )));

        $violations = $this->validator->validateObject($entity, array('Group 1', 'Group 2'));

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testGroupSequenceAbortsAfterFailedGroup()
    {
        $entity = new Entity();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message 1');
        };
        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message 2');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => function () {},
            'groups' => 'Group 1',
        )));
        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group 2',
        )));
        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group 3',
        )));

        $sequence = new GroupSequence(array('Group 1', 'Group 2', 'Group 3'));
        $violations = $this->validator->validateObject($entity, $sequence);

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message 1', $violations[0]->getMessage());
    }

    public function testGroupSequenceIncludesReferences()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Reference violation 1');
        };
        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Reference violation 2');
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group 1',
        )));
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group 2',
        )));

        $sequence = new GroupSequence(array('Group 1', 'Entity'));
        $violations = $this->validator->validateObject($entity, $sequence);

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Reference violation 1', $violations[0]->getMessage());
    }

    public function testValidateInSeparateContext()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $violations = $context
                ->getValidator()
                // Since the validator is not context aware, the group must
                // be passed explicitly
                ->validateObject($value->reference, 'Group')
            ;

            /** @var ConstraintViolationInterface[] $violations */
            $test->assertCount(1, $violations);
            $test->assertSame('Message value', $violations[0]->getMessage());
            $test->assertSame('Message %param%', $violations[0]->getMessageTemplate());
            $test->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
            $test->assertSame('', $violations[0]->getPropertyPath());
            // The root is different as we're in a new context
            $test->assertSame($entity->reference, $violations[0]->getRoot());
            $test->assertSame($entity->reference, $violations[0]->getInvalidValue());
            $test->assertNull($violations[0]->getMessagePluralization());
            $test->assertNull($violations[0]->getCode());

            // Verify that this method is called
            $context->addViolation('Separate violation');
        };

        $callback2 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($entity->reference, $context->getRoot());
            $test->assertSame($entity->reference, $context->getValue());
            $test->assertSame($entity->reference, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group',
        )));
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group',
        )));

        $violations = $this->validator->validateObject($entity, 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $test->assertSame('Separate violation', $violations[0]->getMessage());
    }

    public function testValidateInContext()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context
                ->getValidator()
                ->inContext($context)
                ->atPath('subpath')
                ->validateObject($value->reference)
            ;
        };

        $callback2 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('subpath', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity->reference, $context->getValue());
            $test->assertSame($entity->reference, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group',
        )));
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group',
        )));

        $violations = $this->validator->validateObject($entity, 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
        $this->assertSame('subpath', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getMessagePluralization());
        $this->assertNull($violations[0]->getCode());
    }

    public function testValidateArrayInContext()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context
                ->getValidator()
                ->inContext($context)
                ->atPath('subpath')
                ->validateObjects(array('key' => $value->reference))
            ;
        };

        $callback2 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('subpath[key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity->reference, $context->getValue());
            $test->assertSame($entity->reference, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group',
        )));
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group',
        )));

        $violations = $this->validator->validateObject($entity, 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
        $this->assertSame('subpath[key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getMessagePluralization());
        $this->assertNull($violations[0]->getCode());
    }

    public function testValidateAcceptsValid()
    {
        $test = $this;
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->metadata, $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity, $context->getValue());
            $test->assertSame($entity, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        // This is the same as when calling validateObject()
        $violations = $this->validator->validate($entity, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getMessagePluralization());
        $this->assertNull($violations[0]->getCode());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectTraversableIfTraverse()
    {
        $entity = new Entity();

        $this->validator->validate($entity, new Traverse());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectTraversableIfTraverseOnClass()
    {
        $entity = new Entity();

        $this->metadata->addConstraint(new Traverse());

        $this->validator->validateObject($entity);
    }

    public function testAddCustomizedViolation()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->buildViolation('Message %param%')
                ->setParameter('%param%', 'value')
                ->setInvalidValue('Invalid value')
                ->setPluralization(2)
                ->setCode('Code')
                ->addViolation();
        };

        $this->metadata->addConstraint(new Callback($callback));

        $violations = $this->validator->validateObject($entity);

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Invalid value', $violations[0]->getInvalidValue());
        $this->assertSame(2, $violations[0]->getMessagePluralization());
        $this->assertSame('Code', $violations[0]->getCode());
    }
}
