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
use Symfony\Component\Validator\Context\ExecutionContextInterface;
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

    protected function setUp(): void
    {
        $this->metadataFactory = new FakeMetadataFactory();
        $this->metadata = new ClassMetadata(self::ENTITY_CLASS);
        $this->referenceMetadata = new ClassMetadata(self::REFERENCE_CLASS);
        $this->metadataFactory->addMetadata($this->metadata);
        $this->metadataFactory->addMetadata($this->referenceMetadata);
    }

    protected function tearDown(): void
    {
        $this->metadataFactory = null;
        $this->metadata = null;
        $this->referenceMetadata = null;
    }

    abstract protected function validate($value, $constraints = null, $groups = null): void;

    abstract protected function validateProperty($object, $propertyName, $groups = null): void;

    abstract protected function validatePropertyValue($object, $propertyName, $value, $groups = null): void;

    public function testValidate(): void
    {
        $callback = function ($value, ExecutionContextInterface $context): void {
            $this->assertNull($context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame('Bernhard', $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

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

    public function testClassConstraint(): void
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

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

    public function testPropertyConstraint(): void
    {
        $entity = new Entity();
        $entity->firstName = 'Bernhard';

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('firstName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('firstName', $context->getPropertyName());
            $this->assertSame('firstName', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

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

    public function testGetterConstraint(): void
    {
        $entity = new Entity();
        $entity->setLastName('Schussek');

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('lastName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('lastName', $context->getPropertyName());
            $this->assertSame('lastName', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Schussek', $context->getValue());
            $this->assertSame('Schussek', $value);

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

    public function testArray(): void
    {
        $entity = new Entity();
        $array = array('key' => $entity);

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $array): void {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($array, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

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

    public function testRecursiveArray(): void
    {
        $entity = new Entity();
        $array = array(2 => array('key' => $entity));

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $array): void {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[2][key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($array, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

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

    public function testTraversable(): void
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator(array('key' => $entity));

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $traversable): void {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($traversable, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

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

    public function testRecursiveTraversable(): void
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator(array(
            2 => new \ArrayIterator(array('key' => $entity)),
        ));

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $traversable): void {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[2][key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($traversable, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

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

    public function testReferenceClassConstraint(): void
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference, $context->getValue());
            $this->assertSame($entity->reference, $value);

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

    public function testReferencePropertyConstraint(): void
    {
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference->value = 'Foobar';

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $propertyMetadatas = $this->referenceMetadata->getPropertyMetadata('value');

            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertSame('value', $context->getPropertyName());
            $this->assertSame('reference.value', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Foobar', $context->getValue());
            $this->assertSame('Foobar', $value);

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

    public function testReferenceGetterConstraint(): void
    {
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference->setPrivateValue('Bamboo');

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $propertyMetadatas = $this->referenceMetadata->getPropertyMetadata('privateValue');

            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertSame('privateValue', $context->getPropertyName());
            $this->assertSame('reference.privateValue', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Bamboo', $context->getValue());
            $this->assertSame('Bamboo', $value);

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

    public function testsIgnoreNullReference(): void
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
    public function testFailOnScalarReferences(): void
    {
        $entity = new Entity();
        $entity->reference = 'string';

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->validate($entity);
    }

    public function testArrayReference(): void
    {
        $entity = new Entity();
        $entity->reference = array('key' => new Reference());

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference['key'], $context->getValue());
            $this->assertSame($entity->reference['key'], $value);

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
    public function testRecursiveArrayReference(): void
    {
        $entity = new Entity();
        $entity->reference = array(2 => array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference[2][key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference[2]['key'], $context->getValue());
            $this->assertSame($entity->reference[2]['key'], $value);

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

    public function testArrayTraversalCannotBeDisabled(): void
    {
        $entity = new Entity();
        $entity->reference = array('key' => new Reference());

        $callback = function ($value, ExecutionContextInterface $context): void {
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

    public function testRecursiveArrayTraversalCannotBeDisabled(): void
    {
        $entity = new Entity();
        $entity->reference = array(2 => array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context): void {
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

    public function testIgnoreScalarsDuringArrayTraversal(): void
    {
        $entity = new Entity();
        $entity->reference = array('string', 1234);

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testIgnoreNullDuringArrayTraversal(): void
    {
        $entity = new Entity();
        $entity->reference = array(null);

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testTraversableReference(): void
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference['key'], $context->getValue());
            $this->assertSame($entity->reference['key'], $value);

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

    public function testDisableTraversableTraversal(): void
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Reference()));

        $callback = function ($value, ExecutionContextInterface $context): void {
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
    public function testMetadataMustExistIfTraversalIsDisabled(): void
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator();

        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => false,
        )));

        $this->validate($entity);
    }

    public function testEnableRecursiveTraversableTraversal(): void
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array(
            2 => new \ArrayIterator(array('key' => new Reference())),
        ));

        $callback = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference[2][key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference[2]['key'], $context->getValue());
            $this->assertSame($entity->reference[2]['key'], $value);

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

    public function testValidateProperty(): void
    {
        $entity = new Entity();
        $entity->firstName = 'Bernhard';
        $entity->setLastName('Schussek');

        $callback1 = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('firstName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('firstName', $context->getPropertyName());
            $this->assertSame('firstName', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $callback2 = function ($value, ExecutionContextInterface $context): void {
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
     * https://github.com/symfony/symfony/issues/11604.
     */
    public function testValidatePropertyWithoutConstraints(): void
    {
        $entity = new Entity();
        $violations = $this->validateProperty($entity, 'lastName');

        $this->assertCount(0, $violations, '->validateProperty() returns no violations if no constraints have been configured for the property being validated');
    }

    public function testValidatePropertyValue(): void
    {
        $entity = new Entity();
        $entity->setLastName('Schussek');

        $callback1 = function ($value, ExecutionContextInterface $context) use ($entity): void {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('firstName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('firstName', $context->getPropertyName());
            $this->assertSame('firstName', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $callback2 = function ($value, ExecutionContextInterface $context): void {
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

    public function testValidatePropertyValueWithClassName(): void
    {
        $callback1 = function ($value, ExecutionContextInterface $context): void {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('firstName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('firstName', $context->getPropertyName());
            $this->assertSame('', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame('Bernhard', $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', array('%param%' => 'value'));
        };

        $callback2 = function ($value, ExecutionContextInterface $context): void {
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
     * https://github.com/symfony/symfony/issues/11604.
     */
    public function testValidatePropertyValueWithoutConstraints(): void
    {
        $entity = new Entity();
        $violations = $this->validatePropertyValue($entity, 'lastName', 'foo');

        $this->assertCount(0, $violations, '->validatePropertyValue() returns no violations if no constraints have been configured for the property being validated');
    }

    public function testValidateObjectOnlyOncePerGroup(): void
    {
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference2 = $entity->reference;

        $callback = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Message');
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->metadata->addPropertyConstraint('reference2', new Valid());
        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testValidateDifferentObjectsSeparately(): void
    {
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference2 = new Reference();

        $callback = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Message');
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->metadata->addPropertyConstraint('reference2', new Valid());
        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(2, $violations);
    }

    public function testValidateSingleGroup(): void
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context): void {
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

    public function testValidateMultipleGroups(): void
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context): void {
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

    public function testReplaceDefaultGroupByGroupSequenceObject(): void
    {
        $entity = new Entity();

        $callback1 = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Violation in Group 2');
        };
        $callback2 = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Violation in Group 3');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => function (): void {},
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

    public function testReplaceDefaultGroupByGroupSequenceArray(): void
    {
        $entity = new Entity();

        $callback1 = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Violation in Group 2');
        };
        $callback2 = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Violation in Group 3');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => function (): void {},
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

    public function testPropagateDefaultGroupToReferenceWhenReplacingDefaultGroup(): void
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Violation in Default group');
        };
        $callback2 = function ($value, ExecutionContextInterface $context): void {
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

    public function testValidateCustomGroupWhenDefaultGroupWasReplaced(): void
    {
        $entity = new Entity();

        $callback1 = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Violation in other group');
        };
        $callback2 = function ($value, ExecutionContextInterface $context): void {
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

    /**
     * @dataProvider getTestReplaceDefaultGroup
     */
    public function testReplaceDefaultGroup($sequence, array $assertViolations): void
    {
        $entity = new GroupSequenceProviderEntity($sequence);

        $callback1 = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Violation in Group 2');
        };
        $callback2 = function ($value, ExecutionContextInterface $context): void {
            $context->addViolation('Violation in Group 3');
        };

        $metadata = new ClassMetadata(get_class($entity));
        $metadata->addConstraint(new Callback(array(
            'callback' => function (): void {},
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
        $this->assertCount(count($assertViolations), $violations);
        foreach ($assertViolations as $key => $message) {
            $this->assertSame($message, $violations[$key]->getMessage());
        }
    }

    public function getTestReplaceDefaultGroup()
    {
        return array(
            array(
                'sequence' => new GroupSequence(array('Group 1', 'Group 2', 'Group 3', 'Entity')),
                'assertViolations' => array(
                    'Violation in Group 2',
                ),
            ),
            array(
                'sequence' => array('Group 1', 'Group 2', 'Group 3', 'Entity'),
                'assertViolations' => array(
                    'Violation in Group 2',
                ),
            ),
            array(
                'sequence' => new GroupSequence(array('Group 1', array('Group 2', 'Group 3'), 'Entity')),
                'assertViolations' => array(
                    'Violation in Group 2',
                    'Violation in Group 3',
                ),
            ),
            array(
                'sequence' => array('Group 1', array('Group 2', 'Group 3'), 'Entity'),
                'assertViolations' => array(
                    'Violation in Group 2',
                    'Violation in Group 3',
                ),
            ),
        );
    }
}
