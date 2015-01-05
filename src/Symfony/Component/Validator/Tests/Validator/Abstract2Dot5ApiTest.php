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
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\FailingConstraint;
use Symfony\Component\Validator\Tests\Fixtures\FakeClassMetadata;
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
    abstract protected function createValidator(MetadataFactoryInterface $metadataFactory, array $objectInitializers = array());

    protected function setUp()
    {
        parent::setUp();

        $this->validator = $this->createValidator($this->metadataFactory);
    }

    protected function validate($value, $constraints = null, $groups = null)
    {
        return $this->validator->validate($value, $constraints, $groups);
    }

    protected function validateProperty($object, $propertyName, $groups = null)
    {
        return $this->validator->validateProperty($object, $propertyName, $groups);
    }

    protected function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        return $this->validator->validatePropertyValue($object, $propertyName, $value, $groups);
    }

    public function testValidateConstraintWithoutGroup()
    {
        $violations = $this->validator->validate(null, new NotNull());

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
        $violations = $this->validator->validate($entity, new Valid(), $sequence);

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
        $violations = $this->validator->validate($entity, new Valid(), $sequence);

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
                ->validate($value->reference, new Valid(), 'Group')
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

        $violations = $this->validator->validate($entity, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $test->assertSame('Separate violation', $violations[0]->getMessage());
    }

    public function testValidateInContext()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) use ($test) {
            $previousValue = $context->getValue();
            $previousObject = $context->getObject();
            $previousMetadata = $context->getMetadata();
            $previousPath = $context->getPropertyPath();
            $previousGroup = $context->getGroup();

            $context
                ->getValidator()
                ->inContext($context)
                ->atPath('subpath')
                ->validate($value->reference)
            ;

            // context changes shouldn't leak out of the validate() call
            $test->assertSame($previousValue, $context->getValue());
            $test->assertSame($previousObject, $context->getObject());
            $test->assertSame($previousMetadata, $context->getMetadata());
            $test->assertSame($previousPath, $context->getPropertyPath());
            $test->assertSame($previousGroup, $context->getGroup());
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

        $violations = $this->validator->validate($entity, new Valid(), 'Group');

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

        $callback1 = function ($value, ExecutionContextInterface $context) use ($test) {
            $previousValue = $context->getValue();
            $previousObject = $context->getObject();
            $previousMetadata = $context->getMetadata();
            $previousPath = $context->getPropertyPath();
            $previousGroup = $context->getGroup();

            $context
                ->getValidator()
                ->inContext($context)
                ->atPath('subpath')
                ->validate(array('key' => $value->reference))
            ;

            // context changes shouldn't leak out of the validate() call
            $test->assertSame($previousValue, $context->getValue());
            $test->assertSame($previousObject, $context->getObject());
            $test->assertSame($previousMetadata, $context->getMetadata());
            $test->assertSame($previousPath, $context->getPropertyPath());
            $test->assertSame($previousGroup, $context->getGroup());
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

        $violations = $this->validator->validate($entity, new Valid(), 'Group');

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

    public function testTraverseTraversableByDefault()
    {
        $test = $this;
        $entity = new Entity();
        $traversable = new \ArrayIterator(array('key' => $entity));

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity, $traversable) {
            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('[key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->metadata, $context->getMetadata());
            $test->assertSame($traversable, $context->getRoot());
            $test->assertSame($entity, $context->getValue());
            $test->assertSame($entity, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));
        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($traversable, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
        $this->assertSame('[key]', $violations[0]->getPropertyPath());
        $this->assertSame($traversable, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getMessagePluralization());
        $this->assertNull($violations[0]->getCode());
    }

    public function testTraversalEnabledOnClass()
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator(array('key' => $entity));

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(true));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($traversable, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testTraversalDisabledOnClass()
    {
        $test = $this;
        $entity = new Entity();
        $traversable = new \ArrayIterator(array('key' => $entity));

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->fail('Should not be called');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(false));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($traversable, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\ConstraintDefinitionException
     */
    public function testExpectTraversableIfTraversalEnabledOnClass()
    {
        $entity = new Entity();

        $this->metadata->addConstraint(new Traverse(true));

        $this->validator->validate($entity);
    }

    public function testReferenceTraversalDisabledOnClass()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->fail('Should not be called');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(false));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $violations = $this->validate($entity, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testReferenceTraversalEnabledOnReferenceDisabledOnClass()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->fail('Should not be called');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(false));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));
        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => true,
        )));

        $violations = $this->validate($entity, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testReferenceTraversalDisabledOnReferenceEnabledOnClass()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->fail('Should not be called');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(true));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));
        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => false,
        )));

        $violations = $this->validate($entity, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testReferenceTraversalRecursionEnabledOnReferenceTraversalEnabledOnClass()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array(
            2 => new \ArrayIterator(array('key' => new Reference())),
        ));

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(true));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));
        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'deep' => true,
        )));

        $violations = $this->validate($entity, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testReferenceTraversalRecursionDisabledOnReferenceTraversalEnabledOnClass()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array(
            2 => new \ArrayIterator(array('key' => new Reference())),
        ));

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->fail('Should not be called');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(true));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));
        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'deep' => false,
        )));

        $violations = $this->validate($entity, new Valid(), 'Group');

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testAddCustomizedViolation()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->buildViolation('Message %param%')
                ->setParameter('%param%', 'value')
                ->setInvalidValue('Invalid value')
                ->setPlural(2)
                ->setCode(42)
                ->addViolation();
        };

        $this->metadata->addConstraint(new Callback($callback));

        $violations = $this->validator->validate($entity);

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getMessageParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Invalid value', $violations[0]->getInvalidValue());
        $this->assertSame(2, $violations[0]->getMessagePluralization());
        $this->assertSame(42, $violations[0]->getCode());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnsupportedMetadataException
     */
    public function testMetadataMustImplementClassMetadataInterface()
    {
        $entity = new Entity();

        $metadata = $this->getMock('Symfony\Component\Validator\Tests\Fixtures\LegacyClassMetadata');
        $metadata->expects($this->any())
            ->method('getClassName')
            ->will($this->returnValue(get_class($entity)));

        $this->metadataFactory->addMetadata($metadata);

        $this->validator->validate($entity);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnsupportedMetadataException
     */
    public function testReferenceMetadataMustImplementClassMetadataInterface()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $metadata = $this->getMock('Symfony\Component\Validator\Tests\Fixtures\LegacyClassMetadata');
        $metadata->expects($this->any())
            ->method('getClassName')
            ->will($this->returnValue(get_class($entity->reference)));

        $this->metadataFactory->addMetadata($metadata);

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->validator->validate($entity);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\UnsupportedMetadataException
     */
    public function testLegacyPropertyMetadataMustImplementPropertyMetadataInterface()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);

        $entity = new Entity();

        // Legacy interface
        $propertyMetadata = $this->getMock('Symfony\Component\Validator\MetadataInterface');
        $metadata = new FakeClassMetadata(get_class($entity));
        $metadata->addCustomPropertyMetadata('firstName', $propertyMetadata);

        $this->metadataFactory->addMetadata($metadata);

        $this->validator->validate($entity);
    }

    public function testNoDuplicateValidationIfClassConstraintInMultipleGroups()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => array('Group 1', 'Group 2'),
        )));

        $violations = $this->validator->validate($entity, new Valid(), array('Group 1', 'Group 2'));

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testNoDuplicateValidationIfPropertyConstraintInMultipleGroups()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback(array(
            'callback' => $callback,
            'groups' => array('Group 1', 'Group 2'),
        )));

        $violations = $this->validator->validate($entity, new Valid(), array('Group 1', 'Group 2'));

        /** @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\RuntimeException
     */
    public function testValidateFailsIfNoConstraintsAndNoObjectOrArray()
    {
        $this->validate('Foobar');
    }

    public function testAccessCurrentObject()
    {
        $test = $this;
        $called = false;
        $entity = new Entity();
        $entity->firstName = 'Bernhard';

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity, &$called) {
            $called = true;
            $test->assertSame($entity, $context->getObject());
        };

        $this->metadata->addConstraint(new Callback($callback));
        $this->metadata->addPropertyConstraint('firstName', new Callback($callback));

        $this->validator->validate($entity);

        $this->assertTrue($called);
    }

    public function testInitializeObjectsOnFirstValidation()
    {
        $test = $this;
        $entity = new Entity();
        $entity->initialized = false;

        // prepare initializers that set "initialized" to true
        $initializer1 = $this->getMock('Symfony\\Component\\Validator\\ObjectInitializerInterface');
        $initializer2 = $this->getMock('Symfony\\Component\\Validator\\ObjectInitializerInterface');

        $initializer1->expects($this->once())
            ->method('initialize')
            ->with($entity)
            ->will($this->returnCallback(function ($object) {
                $object->initialized = true;
            }));

        $initializer2->expects($this->once())
            ->method('initialize')
            ->with($entity);

        $this->validator = $this->createValidator($this->metadataFactory, array(
            $initializer1,
            $initializer2,
        ));

        // prepare constraint which
        // * checks that "initialized" is set to true
        // * validates the object again
        $callback = function ($object, ExecutionContextInterface $context) use ($test) {
            $test->assertTrue($object->initialized);

            // validate again in same group
            $validator = $context->getValidator()->inContext($context);

            $validator->validate($object);

            // validate again in other group
            $validator->validate($object, null, 'SomeGroup');
        };

        $this->metadata->addConstraint(new Callback($callback));

        $this->validate($entity);

        $this->assertTrue($entity->initialized);
    }

    public function testPassConstraintToViolation()
    {
        $constraint = new FailingConstraint();
        $violations = $this->validate('Foobar', $constraint);

        $this->assertCount(1, $violations);
        $this->assertSame($constraint, $violations[0]->getConstraint());
    }
}
