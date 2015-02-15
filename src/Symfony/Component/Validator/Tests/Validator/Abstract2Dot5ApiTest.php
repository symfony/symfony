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
<<<<<<< HEAD
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
=======
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) use ($entity) {
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
            $violations = $context
                ->getValidator()
                // Since the validator is not context aware, the group must
                // be passed explicitly
                ->validate($value->reference, new Valid(), 'Group')
            ;

            /** @var ConstraintViolationInterface[] $violations */
<<<<<<< HEAD
            $test->assertCount(1, $violations);
            $test->assertSame('Message value', $violations[0]->getMessage());
            $test->assertSame('Message %param%', $violations[0]->getMessageTemplate());
            $test->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
            $test->assertSame('', $violations[0]->getPropertyPath());
            // The root is different as we're in a new context
            $test->assertSame($entity->reference, $violations[0]->getRoot());
            $test->assertSame($entity->reference, $violations[0]->getInvalidValue());
            $test->assertNull($violations[0]->getPlural());
            $test->assertNull($violations[0]->getCode());
=======
            $this->assertCount(1, $violations);
            $this->assertSame('Message value', $violations[0]->getMessage());
            $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
            $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
            $this->assertSame('', $violations[0]->getPropertyPath());
            // The root is different as we're in a new context
            $this->assertSame($entity->reference, $violations[0]->getRoot());
            $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
            $this->assertNull($violations[0]->getPlural());
            $this->assertNull($violations[0]->getCode());
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

            // Verify that this method is called
            $context->addViolation('Separate violation');
        };

<<<<<<< HEAD
        $callback2 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($entity->reference, $context->getRoot());
            $test->assertSame($entity->reference, $context->getValue());
            $test->assertSame($entity->reference, $value);
=======
        $callback2 = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity->reference, $context->getRoot());
            $this->assertSame($entity->reference, $context->getValue());
            $this->assertSame($entity->reference, $value);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

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
<<<<<<< HEAD
        $test->assertSame('Separate violation', $violations[0]->getMessage());
=======
        $this->assertSame('Separate violation', $violations[0]->getMessage());
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
    }

    public function testValidateInContext()
    {
<<<<<<< HEAD
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) use ($test) {
=======
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
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
<<<<<<< HEAD
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
=======
            $this->assertSame($previousValue, $context->getValue());
            $this->assertSame($previousObject, $context->getObject());
            $this->assertSame($previousMetadata, $context->getMetadata());
            $this->assertSame($previousPath, $context->getPropertyPath());
            $this->assertSame($previousGroup, $context->getGroup());
        };

        $callback2 = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('subpath', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference, $context->getValue());
            $this->assertSame($entity->reference, $value);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

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
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('subpath', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testValidateArrayInContext()
    {
<<<<<<< HEAD
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) use ($test) {
=======
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
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
<<<<<<< HEAD
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
=======
            $this->assertSame($previousValue, $context->getValue());
            $this->assertSame($previousObject, $context->getObject());
            $this->assertSame($previousMetadata, $context->getMetadata());
            $this->assertSame($previousPath, $context->getPropertyPath());
            $this->assertSame($previousGroup, $context->getGroup());
        };

        $callback2 = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('subpath[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference, $context->getValue());
            $this->assertSame($entity->reference, $value);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

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
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('subpath[key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testTraverseTraversableByDefault()
    {
<<<<<<< HEAD
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
=======
        $entity = new Entity();
        $traversable = new \ArrayIterator(array('key' => $entity));

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $traversable) {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($traversable, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

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
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('[key]', $violations[0]->getPropertyPath());
        $this->assertSame($traversable, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
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
<<<<<<< HEAD
        $test = $this;
        $entity = new Entity();
        $traversable = new \ArrayIterator(array('key' => $entity));

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->fail('Should not be called');
=======
        $entity = new Entity();
        $traversable = new \ArrayIterator(array('key' => $entity));

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
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
<<<<<<< HEAD
        $test = $this;
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->fail('Should not be called');
=======
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
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
<<<<<<< HEAD
        $test = $this;
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->fail('Should not be called');
=======
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
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
<<<<<<< HEAD
        $test = $this;
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->fail('Should not be called');
=======
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
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
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Invalid value', $violations[0]->getInvalidValue());
        $this->assertSame(2, $violations[0]->getPlural());
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
<<<<<<< HEAD
        $test = $this;
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
        $called = false;
        $entity = new Entity();
        $entity->firstName = 'Bernhard';

<<<<<<< HEAD
        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity, &$called) {
            $called = true;
            $test->assertSame($entity, $context->getObject());
=======
        $callback = function ($value, ExecutionContextInterface $context) use ($entity, &$called) {
            $called = true;
            $this->assertSame($entity, $context->getObject());
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
        };

        $this->metadata->addConstraint(new Callback($callback));
        $this->metadata->addPropertyConstraint('firstName', new Callback($callback));

        $this->validator->validate($entity);

        $this->assertTrue($called);
    }

    public function testInitializeObjectsOnFirstValidation()
    {
<<<<<<< HEAD
        $test = $this;
=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
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
<<<<<<< HEAD
        $callback = function ($object, ExecutionContextInterface $context) use ($test) {
            $test->assertTrue($object->initialized);
=======
        $callback = function ($object, ExecutionContextInterface $context) {
            $this->assertTrue($object->initialized);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

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
