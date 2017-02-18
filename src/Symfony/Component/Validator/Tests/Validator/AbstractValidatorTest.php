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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity;
use Symfony\Component\Validator\Tests\Fixtures\Reference;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractValidatorTest extends TestCase
{
    const ENTITY_CLASS = 'Symfony\Component\Validator\Tests\Fixtures\Entity';

    const REFERENCE_CLASS = 'Symfony\Component\Validator\Tests\Fixtures\Reference';

    /**
     * @var FakeMetadataFactory
     */
    public $metadataFactory;

    /**
     * @var ClassMetadata
     */
    public $metadata;

    /**
     * @var ClassMetadata
     */
    public $referenceMetadata;

    protected function setUp()
    {
        $this->metadataFactory = new FakeMetadataFactory();
        $this->metadata = new ClassMetadata(self::ENTITY_CLASS);
        $this->referenceMetadata = new ClassMetadata(self::REFERENCE_CLASS);
        $this->metadataFactory->addMetadata($this->metadata);
        $this->metadataFactory->addMetadata($this->referenceMetadata);
    }

    protected function tearDown()
    {
        $this->metadataFactory = null;
        $this->metadata = null;
        $this->referenceMetadata = null;
    }

    abstract protected function validate($value, $constraints = null, $groups = null);

    abstract protected function validateProperty($object, $propertyName, $groups = null);

    abstract protected function validatePropertyValue($object, $propertyName, $value, $groups = null);

    public function testValidate()
    {
        $test = $this;

        $callback = function ($value, ExecutionContextInterface $context) use ($test) {
            $test->assertNull($context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame('Bernhard', $context->getRoot());
            $test->assertSame('Bernhard', $context->getValue());
            $test->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $constraint = new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        ));

        $violations = $this->validate('Bernhard', $constraint, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame('Bernhard', $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testClassConstraint()
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

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testPropertyConstraint()
    {
        $test = $this;
        $entity = new Entity();
        $entity->firstName = 'Bernhard';

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $propertyMetadatas = $test->metadata->getPropertyMetadata('firstName');

            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertSame('firstName', $context->getPropertyName());
            $test->assertSame('firstName', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($propertyMetadatas[0], $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame('Bernhard', $context->getValue());
            $test->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('firstName', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testGetterConstraint()
    {
        $test = $this;
        $entity = new Entity();
        $entity->setLastName('Schussek');

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $propertyMetadatas = $test->metadata->getPropertyMetadata('lastName');

            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertSame('lastName', $context->getPropertyName());
            $test->assertSame('lastName', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($propertyMetadatas[0], $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame('Schussek', $context->getValue());
            $test->assertSame('Schussek', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addGetterConstraint('lastName', new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('lastName', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Schussek', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testArray()
    {
        $test = $this;
        $entity = new Entity();
        $array = array('key' => $entity);

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity, $array) {
            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('[key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->metadata, $context->getMetadata());
            $test->assertSame($array, $context->getRoot());
            $test->assertSame($entity, $context->getValue());
            $test->assertSame($entity, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($array, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('[key]', $violations[0]->getPropertyPath());
        $this->assertSame($array, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testRecursiveArray()
    {
        $test = $this;
        $entity = new Entity();
        $array = array(2 => array('key' => $entity));

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity, $array) {
            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('[2][key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->metadata, $context->getMetadata());
            $test->assertSame($array, $context->getRoot());
            $test->assertSame($entity, $context->getValue());
            $test->assertSame($entity, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($array, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('[2][key]', $violations[0]->getPropertyPath());
        $this->assertSame($array, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testTraversable()
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

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($traversable, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
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

    public function testRecursiveTraversable()
    {
        $test = $this;
        $entity = new Entity();
        $traversable = new \ArrayIterator(array(
            2 => new \ArrayIterator(array('key' => $entity)),
        ));

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity, $traversable) {
            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('[2][key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->metadata, $context->getMetadata());
            $test->assertSame($traversable, $context->getRoot());
            $test->assertSame($entity, $context->getValue());
            $test->assertSame($entity, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($traversable, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('[2][key]', $violations[0]->getPropertyPath());
        $this->assertSame($traversable, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testReferenceClassConstraint()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('reference', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity->reference, $context->getValue());
            $test->assertSame($entity->reference, $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('reference', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testReferencePropertyConstraint()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference->value = 'Foobar';

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $propertyMetadatas = $test->referenceMetadata->getPropertyMetadata('value');

            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertSame('value', $context->getPropertyName());
            $test->assertSame('reference.value', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($propertyMetadatas[0], $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame('Foobar', $context->getValue());
            $test->assertSame('Foobar', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addPropertyConstraint('value', new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('reference.value', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Foobar', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testReferenceGetterConstraint()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference->setPrivateValue('Bamboo');

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $propertyMetadatas = $test->referenceMetadata->getPropertyMetadata('privateValue');

            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertSame('privateValue', $context->getPropertyName());
            $test->assertSame('reference.privateValue', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($propertyMetadatas[0], $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame('Bamboo', $context->getValue());
            $test->assertSame('Bamboo', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addPropertyConstraint('privateValue', new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('reference.privateValue', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Bamboo', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testsIgnoreNullReference()
    {
        $entity = new Entity();
        $entity->reference = null;

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\NoSuchMetadataException
     */
    public function testFailOnScalarReferences()
    {
        $entity = new Entity();
        $entity->reference = 'string';

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->validate($entity);
    }

    public function testArrayReference()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = array('key' => new Reference());

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('reference[key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity->reference['key'], $context->getValue());
            $test->assertSame($entity->reference['key'], $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('reference[key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference['key'], $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    // https://github.com/symfony/symfony/issues/6246
    public function testRecursiveArrayReference()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = array(2 => array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('reference[2][key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity->reference[2]['key'], $context->getValue());
            $test->assertSame($entity->reference[2]['key'], $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('reference[2][key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference[2]['key'], $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testArrayTraversalCannotBeDisabled()
    {
        $entity = new Entity();
        $entity->reference = array('key' => new Reference());

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => false,
        )));
        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testRecursiveArrayTraversalCannotBeDisabled()
    {
        $entity = new Entity();
        $entity->reference = array(2 => array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => false,
        )));
        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testIgnoreScalarsDuringArrayTraversal()
    {
        $entity = new Entity();
        $entity->reference = array('string', 1234);

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testIgnoreNullDuringArrayTraversal()
    {
        $entity = new Entity();
        $entity->reference = array(null);

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testTraversableReference()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('reference[key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity->reference['key'], $context->getValue());
            $test->assertSame($entity->reference['key'], $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('reference[key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference['key'], $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testDisableTraversableTraversal()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));
        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => false,
        )));
        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\NoSuchMetadataException
     */
    public function testMetadataMustExistIfTraversalIsDisabled()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator();

        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => false,
        )));

        $this->validate($entity);
    }

    public function testEnableRecursiveTraversableTraversal()
    {
        $test = $this;
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array(
            2 => new \ArrayIterator(array('key' => new Reference())),
        ));

        $callback = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $test->assertSame($test::REFERENCE_CLASS, $context->getClassName());
            $test->assertNull($context->getPropertyName());
            $test->assertSame('reference[2][key]', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($test->referenceMetadata, $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame($entity->reference[2]['key'], $context->getValue());
            $test->assertSame($entity->reference[2]['key'], $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => true,
        )));
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('reference[2][key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference[2]['key'], $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testValidateProperty()
    {
        $test = $this;
        $entity = new Entity();
        $entity->firstName = 'Bernhard';
        $entity->setLastName('Schussek');

        $callback1 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $propertyMetadatas = $test->metadata->getPropertyMetadata('firstName');

            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertSame('firstName', $context->getPropertyName());
            $test->assertSame('firstName', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($propertyMetadatas[0], $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame('Bernhard', $context->getValue());
            $test->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Other violation');
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group',
        )));
        $this->metadata->addPropertyConstraint('lastName', new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group',
        )));

        $violations = $this->validateProperty($entity, 'firstName', 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('firstName', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    /**
     * Cannot be UnsupportedMetadataException for BC with Symfony < 2.5.
     *
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     * @group legacy
     */
    public function testLegacyValidatePropertyFailsIfPropertiesNotSupported()
    {
        // $metadata does not implement PropertyMetadataContainerInterface
        $metadata = $this->getMockBuilder('Symfony\Component\Validator\MetadataInterface')->getMock();

        $this->metadataFactory->addMetadataForValue('VALUE', $metadata);

        $this->validateProperty('VALUE', 'someProperty');
    }

    /**
     * https://github.com/symfony/symfony/issues/11604.
     */
    public function testValidatePropertyWithoutConstraints()
    {
        $entity = new Entity();
        $violations = $this->validateProperty($entity, 'lastName');

        $this->assertCount(0, $violations, '->validateProperty() returns no violations if no constraints have been configured for the property being validated');
    }

    public function testValidatePropertyValue()
    {
        $test = $this;
        $entity = new Entity();
        $entity->setLastName('Schussek');

        $callback1 = function ($value, ExecutionContextInterface $context) use ($test, $entity) {
            $propertyMetadatas = $test->metadata->getPropertyMetadata('firstName');

            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertSame('firstName', $context->getPropertyName());
            $test->assertSame('firstName', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($propertyMetadatas[0], $context->getMetadata());
            $test->assertSame($entity, $context->getRoot());
            $test->assertSame('Bernhard', $context->getValue());
            $test->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Other violation');
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group',
        )));
        $this->metadata->addPropertyConstraint('lastName', new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group',
        )));

        $violations = $this->validatePropertyValue(
            $entity,
            'firstName',
            'Bernhard',
            'Group'
        );

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('firstName', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testValidatePropertyValueWithClassName()
    {
        $test = $this;

        $callback1 = function ($value, ExecutionContextInterface $context) use ($test) {
            $propertyMetadatas = $test->metadata->getPropertyMetadata('firstName');

            $test->assertSame($test::ENTITY_CLASS, $context->getClassName());
            $test->assertSame('firstName', $context->getPropertyName());
            $test->assertSame('', $context->getPropertyPath());
            $test->assertSame('Group', $context->getGroup());
            $test->assertSame($propertyMetadatas[0], $context->getMetadata());
            $test->assertSame('Bernhard', $context->getRoot());
            $test->assertSame('Bernhard', $context->getValue());
            $test->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Other violation');
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group',
        )));
        $this->metadata->addPropertyConstraint('lastName', new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group',
        )));

        $violations = $this->validatePropertyValue(
            self::ENTITY_CLASS,
            'firstName',
            'Bernhard',
            'Group'
        );

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame('Bernhard', $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    /**
     * Cannot be UnsupportedMetadataException for BC with Symfony < 2.5.
     *
     * @expectedException \Symfony\Component\Validator\Exception\ValidatorException
     * @group legacy
     */
    public function testLegacyValidatePropertyValueFailsIfPropertiesNotSupported()
    {
        // $metadata does not implement PropertyMetadataContainerInterface
        $metadata = $this->getMockBuilder('Symfony\Component\Validator\MetadataInterface')->getMock();

        $this->metadataFactory->addMetadataForValue('VALUE', $metadata);

        $this->validatePropertyValue('VALUE', 'someProperty', 'someValue');
    }

    /**
     * https://github.com/symfony/symfony/issues/11604.
     */
    public function testValidatePropertyValueWithoutConstraints()
    {
        $entity = new Entity();
        $violations = $this->validatePropertyValue($entity, 'lastName', 'foo');

        $this->assertCount(0, $violations, '->validatePropertyValue() returns no violations if no constraints have been configured for the property being validated');
    }

    public function testValidateObjectOnlyOncePerGroup()
    {
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference2 = $entity->reference;

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->metadata->addPropertyConstraint('reference2', new Valid());
        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testValidateDifferentObjectsSeparately()
    {
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference2 = new Reference();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->metadata->addPropertyConstraint('reference2', new Valid());
        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(2, $violations);
    }

    public function testValidateSingleGroup()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group 1',
        )));
        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group 2',
        )));

        $violations = $this->validate($entity, null, 'Group 2');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testValidateMultipleGroups()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group 1',
        )));
        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group 2',
        )));

        $violations = $this->validate($entity, null, array('Group 1', 'Group 2'));

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(2, $violations);
    }

    public function testReplaceDefaultGroupByGroupSequenceObject()
    {
        $entity = new Entity();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 2');
        };
        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 3');
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

        $sequence = new GroupSequence(array('Group 1', 'Group 2', 'Group 3', 'Entity'));
        $this->metadata->setGroupSequence($sequence);

        $violations = $this->validate($entity, null, 'Default');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Violation in Group 2', $violations[0]->getMessage());
    }

    public function testReplaceDefaultGroupByGroupSequenceArray()
    {
        $entity = new Entity();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 2');
        };
        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 3');
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

        $sequence = array('Group 1', 'Group 2', 'Group 3', 'Entity');
        $this->metadata->setGroupSequence($sequence);

        $violations = $this->validate($entity, null, 'Default');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Violation in Group 2', $violations[0]->getMessage());
    }

    public function testPropagateDefaultGroupToReferenceWhenReplacingDefaultGroup()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Default group');
        };
        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in group sequence');
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Default',
        )));
        $this->referenceMetadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group 1',
        )));

        $sequence = new GroupSequence(array('Group 1', 'Entity'));
        $this->metadata->setGroupSequence($sequence);

        $violations = $this->validate($entity, null, 'Default');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Violation in Default group', $violations[0]->getMessage());
    }

    public function testValidateCustomGroupWhenDefaultGroupWasReplaced()
    {
        $entity = new Entity();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in other group');
        };
        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in group sequence');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Other Group',
        )));
        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group 1',
        )));

        $sequence = new GroupSequence(array('Group 1', 'Entity'));
        $this->metadata->setGroupSequence($sequence);

        $violations = $this->validate($entity, null, 'Other Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Violation in other group', $violations[0]->getMessage());
    }

    public function testReplaceDefaultGroupWithObjectFromGroupSequenceProvider()
    {
        $sequence = new GroupSequence(array('Group 1', 'Group 2', 'Group 3', 'Entity'));
        $entity = new GroupSequenceProviderEntity($sequence);

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 2');
        };
        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 3');
        };

        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addConstraint(new Callback(array(
            'callback' => function () {},
            'groups' => 'Group 1',
        )));
        $metadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group 2',
        )));
        $metadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group 3',
        )));
        $metadata->setGroupSequenceProvider(true);

        $this->metadataFactory->addMetadata($metadata);

        $violations = $this->validate($entity, null, 'Default');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Violation in Group 2', $violations[0]->getMessage());
    }

    public function testReplaceDefaultGroupWithArrayFromGroupSequenceProvider()
    {
        $sequence = array('Group 1', 'Group 2', 'Group 3', 'Entity');
        $entity = new GroupSequenceProviderEntity($sequence);

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 2');
        };
        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 3');
        };

        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addConstraint(new Callback(array(
            'callback' => function () {},
            'groups' => 'Group 1',
        )));
        $metadata->addConstraint(new Callback(array(
            'callback' => $callback1,
            'groups' => 'Group 2',
        )));
        $metadata->addConstraint(new Callback(array(
            'callback' => $callback2,
            'groups' => 'Group 3',
        )));
        $metadata->setGroupSequenceProvider(true);

        $this->metadataFactory->addMetadata($metadata);

        $violations = $this->validate($entity, null, 'Default');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Violation in Group 2', $violations[0]->getMessage());
    }
}
