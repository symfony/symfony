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
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Cascade;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\IsFalse;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\NoSuchMetadataException;
use Symfony\Component\Validator\Exception\RuntimeException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\MetadataFactoryInterface;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\ChildA;
use Symfony\Component\Validator\Tests\Constraints\Fixtures\ChildB;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\EntityParent;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\GroupSequenceProviderEntity;
use Symfony\Component\Validator\Tests\Fixtures\CascadedChild;
use Symfony\Component\Validator\Tests\Fixtures\CascadingEntity;
use Symfony\Component\Validator\Tests\Fixtures\EntityWithGroupedConstraintOnMethods;
use Symfony\Component\Validator\Tests\Fixtures\FailingConstraint;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\Tests\Fixtures\Reference;
use Symfony\Component\Validator\Validator\ContextualValidatorInterface;
use Symfony\Component\Validator\Validator\LazyProperty;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RecursiveValidatorTest extends TestCase
{
    private const ENTITY_CLASS = Entity::class;
    private const REFERENCE_CLASS = Reference::class;

    /**
     * @var FakeMetadataFactory
     */
    private $metadataFactory;

    /**
     * @var ClassMetadata
     */
    private $metadata;

    /**
     * @var ClassMetadata
     */
    private $referenceMetadata;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    protected function setUp(): void
    {
        $this->metadataFactory = new FakeMetadataFactory();
        $this->metadata = new ClassMetadata(self::ENTITY_CLASS);
        $this->referenceMetadata = new ClassMetadata(self::REFERENCE_CLASS);
        $this->metadataFactory->addMetadata($this->metadata);
        $this->metadataFactory->addMetadata($this->referenceMetadata);
        $this->metadataFactory->addMetadata(new ClassMetadata(LazyProperty::class));

        $this->validator = $this->createValidator($this->metadataFactory);
    }

    protected function tearDown(): void
    {
        $this->metadataFactory = null;
        $this->metadata = null;
        $this->referenceMetadata = null;
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

    public function testValidate()
    {
        $callback = function ($value, ExecutionContextInterface $context) {
            $this->assertNull($context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame('Bernhard', $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $constraint = new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]);

        $violations = $this->validate('Bernhard', $constraint, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame('Bernhard', $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testClassConstraint()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testPropertyConstraint()
    {
        $entity = new Entity();
        $entity->firstName = 'Bernhard';

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('firstName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('firstName', $context->getPropertyName());
            $this->assertSame('firstName', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('firstName', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testGetterConstraint()
    {
        $entity = new Entity();
        $entity->setLastName('Schussek');

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('lastName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('lastName', $context->getPropertyName());
            $this->assertSame('lastName', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Schussek', $context->getValue());
            $this->assertSame('Schussek', $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addGetterConstraint('lastName', new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('lastName', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Schussek', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testArray()
    {
        $entity = new Entity();
        $array = ['key' => $entity];

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $array) {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($array, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($array, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('[key]', $violations[0]->getPropertyPath());
        $this->assertSame($array, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testRecursiveArray()
    {
        $entity = new Entity();
        $array = [2 => ['key' => $entity]];

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $array) {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[2][key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($array, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($array, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('[2][key]', $violations[0]->getPropertyPath());
        $this->assertSame($array, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testTraversable()
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator(['key' => $entity]);

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $traversable) {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($traversable, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($traversable, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('[key]', $violations[0]->getPropertyPath());
        $this->assertSame($traversable, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testRecursiveTraversable()
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator([
            2 => new \ArrayIterator(['key' => $entity]),
        ]);

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $traversable) {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[2][key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($traversable, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($traversable, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('[2][key]', $violations[0]->getPropertyPath());
        $this->assertSame($traversable, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testReferenceClassConstraint()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference, $context->getValue());
            $this->assertSame($entity->reference, $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('reference', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testReferencePropertyConstraint()
    {
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference->value = 'Foobar';

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $propertyMetadatas = $this->referenceMetadata->getPropertyMetadata('value');

            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertSame('value', $context->getPropertyName());
            $this->assertSame('reference.value', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Foobar', $context->getValue());
            $this->assertSame('Foobar', $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addPropertyConstraint('value', new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('reference.value', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Foobar', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testReferenceGetterConstraint()
    {
        $entity = new Entity();
        $entity->reference = new Reference();
        $entity->reference->setPrivateValue('Bamboo');

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $propertyMetadatas = $this->referenceMetadata->getPropertyMetadata('privateValue');

            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertSame('privateValue', $context->getPropertyName());
            $this->assertSame('reference.privateValue', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Bamboo', $context->getValue());
            $this->assertSame('Bamboo', $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addPropertyConstraint('privateValue', new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
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

    public function testFailOnScalarReferences()
    {
        $this->expectException(NoSuchMetadataException::class);
        $entity = new Entity();
        $entity->reference = 'string';

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->validate($entity);
    }

    /**
     * @dataProvider getConstraintMethods
     */
    public function testArrayReference($constraintMethod)
    {
        $entity = new Entity();
        $entity->reference = ['key' => new Reference()];

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference['key'], $context->getValue());
            $this->assertSame($entity->reference['key'], $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->$constraintMethod('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('reference[key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference['key'], $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    /**
     * @dataProvider getConstraintMethods
     */
    public function testRecursiveArrayReference($constraintMethod)
    {
        $entity = new Entity();
        $entity->reference = [2 => ['key' => new Reference()]];

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference[2][key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference[2]['key'], $context->getValue());
            $this->assertSame($entity->reference[2]['key'], $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->$constraintMethod('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('reference[2][key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference[2]['key'], $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testOnlyCascadedArraysAreTraversed()
    {
        $entity = new Entity();
        $entity->reference = ['key' => new Reference()];

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addPropertyConstraint('reference', new Callback([
            'callback' => function () {},
            'groups' => 'Group',
        ]));
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    /**
     * @dataProvider getConstraintMethods
     */
    public function testArrayTraversalCannotBeDisabled($constraintMethod)
    {
        $entity = new Entity();
        $entity->reference = ['key' => new Reference()];

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->$constraintMethod('reference', new Valid([
            'traverse' => false,
        ]));
        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    /**
     * @dataProvider getConstraintMethods
     */
    public function testRecursiveArrayTraversalCannotBeDisabled($constraintMethod)
    {
        $entity = new Entity();
        $entity->reference = [2 => ['key' => new Reference()]];

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->$constraintMethod('reference', new Valid([
            'traverse' => false,
        ]));

        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    /**
     * @dataProvider getConstraintMethods
     */
    public function testIgnoreScalarsDuringArrayTraversal($constraintMethod)
    {
        $entity = new Entity();
        $entity->reference = ['string', 1234];

        $this->metadata->$constraintMethod('reference', new Valid());

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    /**
     * @dataProvider getConstraintMethods
     */
    public function testIgnoreNullDuringArrayTraversal($constraintMethod)
    {
        $entity = new Entity();
        $entity->reference = [null];

        $this->metadata->$constraintMethod('reference', new Valid());

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testTraversableReference()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(['key' => new Reference()]);

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference['key'], $context->getValue());
            $this->assertSame($entity->reference['key'], $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('reference[key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference['key'], $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testDisableTraversableTraversal()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(['key' => new Reference()]);

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));
        $this->metadata->addPropertyConstraint('reference', new Valid([
            'traverse' => false,
        ]));
        $this->referenceMetadata->addConstraint(new Callback($callback));

        $violations = $this->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testMetadataMustExistIfTraversalIsDisabled()
    {
        $this->expectException(NoSuchMetadataException::class);
        $entity = new Entity();
        $entity->reference = new \ArrayIterator();

        $this->metadata->addPropertyConstraint('reference', new Valid([
            'traverse' => false,
        ]));

        $this->validate($entity);
    }

    public function testEnableRecursiveTraversableTraversal()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator([
            2 => new \ArrayIterator(['key' => new Reference()]),
        ]);

        $callback = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('reference[2][key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference[2]['key'], $context->getValue());
            $this->assertSame($entity->reference[2]['key'], $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addPropertyConstraint('reference', new Valid([
            'traverse' => true,
        ]));
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, null, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('reference[2][key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference[2]['key'], $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testValidateProperty()
    {
        $entity = new Entity();
        $entity->firstName = 'Bernhard';
        $entity->setLastName('Schussek');

        $callback1 = function ($value, ExecutionContextInterface $context) use ($entity) {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('firstName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('firstName', $context->getPropertyName());
            $this->assertSame('firstName', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Other violation');
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback([
            'callback' => $callback1,
            'groups' => 'Group',
        ]));
        $this->metadata->addPropertyConstraint('lastName', new Callback([
            'callback' => $callback2,
            'groups' => 'Group',
        ]));

        $violations = $this->validateProperty($entity, 'firstName', 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('firstName', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
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
        $entity = new Entity();
        $entity->setLastName('Schussek');

        $callback1 = function ($value, ExecutionContextInterface $context) use ($entity) {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('firstName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('firstName', $context->getPropertyName());
            $this->assertSame('firstName', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Other violation');
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback([
            'callback' => $callback1,
            'groups' => 'Group',
        ]));
        $this->metadata->addPropertyConstraint('lastName', new Callback([
            'callback' => $callback2,
            'groups' => 'Group',
        ]));

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
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('firstName', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testValidatePropertyValueWithClassName()
    {
        $callback1 = function ($value, ExecutionContextInterface $context) {
            $propertyMetadatas = $this->metadata->getPropertyMetadata('firstName');

            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertSame('firstName', $context->getPropertyName());
            $this->assertSame('', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($propertyMetadatas[0], $context->getMetadata());
            $this->assertSame('Bernhard', $context->getRoot());
            $this->assertSame('Bernhard', $context->getValue());
            $this->assertSame('Bernhard', $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Other violation');
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback([
            'callback' => $callback1,
            'groups' => 'Group',
        ]));
        $this->metadata->addPropertyConstraint('lastName', new Callback([
            'callback' => $callback2,
            'groups' => 'Group',
        ]));

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
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame('Bernhard', $violations[0]->getRoot());
        $this->assertSame('Bernhard', $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
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

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group 1',
        ]));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group 2',
        ]));

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

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group 1',
        ]));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group 2',
        ]));

        $violations = $this->validate($entity, null, ['Group 1', 'Group 2']);

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

        $this->metadata->addConstraint(new Callback([
            'callback' => function () {},
            'groups' => 'Group 1',
        ]));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Group 2',
        ]));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group 3',
        ]));

        $sequence = new GroupSequence(['Group 1', 'Group 2', 'Group 3', 'Entity']);
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

        $this->metadata->addConstraint(new Callback([
            'callback' => function () {},
            'groups' => 'Group 1',
        ]));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Group 2',
        ]));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group 3',
        ]));

        $sequence = ['Group 1', 'Group 2', 'Group 3', 'Entity'];
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
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Default',
        ]));
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group 1',
        ]));

        $sequence = new GroupSequence(['Group 1', 'Entity']);
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

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Other Group',
        ]));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group 1',
        ]));

        $sequence = new GroupSequence(['Group 1', 'Entity']);
        $this->metadata->setGroupSequence($sequence);

        $violations = $this->validate($entity, null, 'Other Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Violation in other group', $violations[0]->getMessage());
    }

    /**
     * @dataProvider getTestReplaceDefaultGroup
     */
    public function testReplaceDefaultGroup($sequence, array $assertViolations)
    {
        $entity = new GroupSequenceProviderEntity($sequence);

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 2');
        };
        $callback2 = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Violation in Group 3');
        };

        $metadata = new ClassMetadata($entity::class);
        $metadata->addConstraint(new Callback([
            'callback' => function () {},
            'groups' => 'Group 1',
        ]));
        $metadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Group 2',
        ]));
        $metadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group 3',
        ]));
        $metadata->setGroupSequenceProvider(true);

        $this->metadataFactory->addMetadata($metadata);

        $violations = $this->validate($entity, null, 'Default');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(\count($assertViolations), $violations);
        foreach ($assertViolations as $key => $message) {
            $this->assertSame($message, $violations[$key]->getMessage());
        }
    }

    public static function getConstraintMethods()
    {
        return [
            ['addPropertyConstraint'],
            ['addGetterConstraint'],
        ];
    }

    public static function getTestReplaceDefaultGroup()
    {
        return [
            [
                'sequence' => new GroupSequence(['Group 1', 'Group 2', 'Group 3', 'Entity']),
                'assertViolations' => [
                    'Violation in Group 2',
                ],
            ],
            [
                'sequence' => ['Group 1', 'Group 2', 'Group 3', 'Entity'],
                'assertViolations' => [
                    'Violation in Group 2',
                ],
            ],
            [
                'sequence' => new GroupSequence(['Group 1', ['Group 2', 'Group 3'], 'Entity']),
                'assertViolations' => [
                    'Violation in Group 2',
                    'Violation in Group 3',
                ],
            ],
            [
                'sequence' => ['Group 1', ['Group 2', 'Group 3'], 'Entity'],
                'assertViolations' => [
                    'Violation in Group 2',
                    'Violation in Group 3',
                ],
            ],
        ];
    }

    public function testValidateConstraintWithoutGroup()
    {
        $violations = $this->validator->validate(null, new NotNull());

        $this->assertCount(1, $violations);
    }

    public function testValidateWithEmptyArrayAsConstraint()
    {
        $violations = $this->validator->validate('value', []);
        $this->assertCount(0, $violations);
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

        $this->metadata->addConstraint(new Callback([
            'callback' => function () {},
            'groups' => 'Group 1',
        ]));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Group 2',
        ]));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group 3',
        ]));

        $sequence = new GroupSequence(['Group 1', 'Group 2', 'Group 3']);
        $violations = $this->validator->validate($entity, new Valid(), $sequence);

        /* @var ConstraintViolationInterface[] $violations */
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
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Group 1',
        ]));
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group 2',
        ]));

        $sequence = new GroupSequence(['Group 1', 'Entity']);
        $violations = $this->validator->validate($entity, new Valid(), $sequence);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Reference violation 1', $violations[0]->getMessage());
    }

    public function testValidateInSeparateContext()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) use ($entity) {
            $violations = $context
                ->getValidator()
                // Since the validator is not context aware, the group must
                // be passed explicitly
                ->validate($value->reference, new Valid(), 'Group')
            ;

            /* @var ConstraintViolationInterface[] $violations */
            $this->assertCount(1, $violations);
            $this->assertSame('Message value', $violations[0]->getMessage());
            $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
            $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
            $this->assertSame('', $violations[0]->getPropertyPath());

            // The root is different as we're in a new context
            $this->assertSame($entity->reference, $violations[0]->getRoot());
            $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
            $this->assertNull($violations[0]->getPlural());
            $this->assertNull($violations[0]->getCode());

            // Verify that this method is called
            $context->addViolation('Separate violation');
        };

        $callback2 = function ($value, ExecutionContextInterface $context) use ($entity) {
            $this->assertSame($this::REFERENCE_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->referenceMetadata, $context->getMetadata());
            $this->assertSame($entity->reference, $context->getRoot());
            $this->assertSame($entity->reference, $context->getValue());
            $this->assertSame($entity->reference, $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Group',
        ]));
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group',
        ]));

        $violations = $this->validator->validate($entity, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Separate violation', $violations[0]->getMessage());
    }

    public function testValidateInContext()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
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

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Group',
        ]));
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group',
        ]));

        $violations = $this->validator->validate($entity, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('subpath', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testValidateArrayInContext()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $previousValue = $context->getValue();
            $previousObject = $context->getObject();
            $previousMetadata = $context->getMetadata();
            $previousPath = $context->getPropertyPath();
            $previousGroup = $context->getGroup();

            $context
                ->getValidator()
                ->inContext($context)
                ->atPath('subpath')
                ->validate(['key' => $value->reference])
            ;

            // context changes shouldn't leak out of the validate() call
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

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback1,
            'groups' => 'Group',
        ]));
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback2,
            'groups' => 'Group',
        ]));

        $violations = $this->validator->validate($entity, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('subpath[key]', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame($entity->reference, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testTraverseTraversableByDefault()
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator(['key' => $entity]);

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, $traversable) {
            $this->assertSame($this::ENTITY_CLASS, $context->getClassName());
            $this->assertNull($context->getPropertyName());
            $this->assertSame('[key]', $context->getPropertyPath());
            $this->assertSame('Group', $context->getGroup());
            $this->assertSame($this->metadata, $context->getMetadata());
            $this->assertSame($traversable, $context->getRoot());
            $this->assertSame($entity, $context->getValue());
            $this->assertSame($entity, $value);

            $context->addViolation('Message %param%', ['%param%' => 'value']);
        };

        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($traversable, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('[key]', $violations[0]->getPropertyPath());
        $this->assertSame($traversable, $violations[0]->getRoot());
        $this->assertSame($entity, $violations[0]->getInvalidValue());
        $this->assertNull($violations[0]->getPlural());
        $this->assertNull($violations[0]->getCode());
    }

    public function testTraversalEnabledOnClass()
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator(['key' => $entity]);

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(true));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($traversable, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testTraversalDisabledOnClass()
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator(['key' => $entity]);

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(false));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($traversable, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testExpectTraversableIfTraversalEnabledOnClass()
    {
        $this->expectException(ConstraintDefinitionException::class);
        $entity = new Entity();

        $this->metadata->addConstraint(new Traverse(true));

        $this->validator->validate($entity);
    }

    public function testReferenceTraversalDisabledOnClass()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(['key' => new Reference()]);

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(false));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $violations = $this->validate($entity, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testReferenceTraversalEnabledOnReferenceDisabledOnClass()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(['key' => new Reference()]);

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(false));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));
        $this->metadata->addPropertyConstraint('reference', new Valid([
            'traverse' => true,
        ]));

        $violations = $this->validate($entity, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testReferenceTraversalDisabledOnReferenceEnabledOnClass()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(['key' => new Reference()]);

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
        };

        $traversableMetadata = new ClassMetadata('ArrayIterator');
        $traversableMetadata->addConstraint(new Traverse(true));

        $this->metadataFactory->addMetadata($traversableMetadata);
        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));
        $this->metadata->addPropertyConstraint('reference', new Valid([
            'traverse' => false,
        ]));

        $violations = $this->validate($entity, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testReferenceCascadeDisabledByDefault()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
        };

        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testReferenceCascadeEnabledIgnoresUntyped()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $this->metadata->addConstraint(new Cascade());

        $callback = function ($value, ExecutionContextInterface $context) {
            $this->fail('Should not be called');
        };

        $this->referenceMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $violations = $this->validate($entity, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(0, $violations);
    }

    public function testTypedReferenceCascadeEnabled()
    {
        $entity = new CascadingEntity();
        $entity->requiredChild = new CascadedChild();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->buildViolation('Invalid child')
                ->atPath('name')
                ->addViolation()
            ;
        };

        $cascadingMetadata = new ClassMetadata(CascadingEntity::class);
        $cascadingMetadata->addConstraint(new Cascade());

        $cascadedMetadata = new ClassMetadata(CascadedChild::class);
        $cascadedMetadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => 'Group',
        ]));

        $this->metadataFactory->addMetadata($cascadingMetadata);
        $this->metadataFactory->addMetadata($cascadedMetadata);

        $violations = $this->validate($entity, new Valid(), 'Group');

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertInstanceOf(Callback::class, $violations->get(0)->getConstraint());
    }

    public function testAddCustomizedViolation()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->buildViolation('Message %param%')
                ->setParameter('%param%', 'value')
                ->setInvalidValue('Invalid value')
                ->setPlural(2)
                ->setCode('42')
                ->addViolation();
        };

        $this->metadata->addConstraint(new Callback($callback));

        $violations = $this->validator->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(['%param%' => 'value'], $violations[0]->getParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Invalid value', $violations[0]->getInvalidValue());
        $this->assertSame(2, $violations[0]->getPlural());
        $this->assertSame('42', $violations[0]->getCode());
    }

    public function testNoDuplicateValidationIfClassConstraintInMultipleGroups()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $this->metadata->addConstraint(new Callback([
            'callback' => $callback,
            'groups' => ['Group 1', 'Group 2'],
        ]));

        $violations = $this->validator->validate($entity, new Valid(), ['Group 1', 'Group 2']);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testNoDuplicateValidationIfPropertyConstraintInMultipleGroups()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation('Message');
        };

        $this->metadata->addPropertyConstraint('firstName', new Callback([
            'callback' => $callback,
            'groups' => ['Group 1', 'Group 2'],
        ]));

        $violations = $this->validator->validate($entity, new Valid(), ['Group 1', 'Group 2']);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
    }

    public function testValidateFailsIfNoConstraintsAndNoObjectOrArray()
    {
        $this->expectException(RuntimeException::class);
        $this->validate('Foobar');
    }

    public function testAccessCurrentObject()
    {
        $called = false;
        $entity = new Entity();
        $entity->firstName = 'Bernhard';
        $entity->data = ['firstName' => 'Bernhard'];

        $callback = function ($value, ExecutionContextInterface $context) use ($entity, &$called) {
            $called = true;
            $this->assertSame($entity, $context->getObject());
        };

        $this->metadata->addConstraint(new Callback($callback));
        $this->metadata->addPropertyConstraint('firstName', new Callback($callback));
        $this->metadata->addPropertyConstraint('data', new Collection(['firstName' => new Expression('value == this.firstName')]));

        $this->validator->validate($entity);

        $this->assertTrue($called);
    }

    public function testInitializeObjectsOnFirstValidation()
    {
        $entity = new Entity();
        $entity->initialized = false;

        // prepare initializers that set "initialized" to true
        $initializer1 = $this->createMock(ObjectInitializerInterface::class);
        $initializer2 = $this->createMock(ObjectInitializerInterface::class);

        $initializer1->expects($this->once())
            ->method('initialize')
            ->with($entity)
            ->willReturnCallback(function ($object) {
                $object->initialized = true;
            });

        $initializer2->expects($this->once())
            ->method('initialize')
            ->with($entity);

        $this->validator = $this->createValidator($this->metadataFactory, [
            $initializer1,
            $initializer2,
        ]);

        // prepare constraint which
        // * checks that "initialized" is set to true
        // * validates the object again
        $callback = function ($object, ExecutionContextInterface $context) {
            $this->assertTrue($object->initialized);

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

    public function testCollectionConstraintViolationHasCorrectContext()
    {
        $data = [
            'foo' => 'fooValue',
        ];

        // Missing field must not be the first in the collection validation
        $constraint = new Collection([
            'foo' => new NotNull(),
            'bar' => new NotNull(),
        ]);

        $violations = $this->validate($data, $constraint);

        $this->assertCount(1, $violations);
        $this->assertSame($constraint, $violations[0]->getConstraint());
    }

    public function testNestedObjectIsNotValidatedIfGroupInValidConstraintIsNotValidated()
    {
        $entity = new Entity();
        $entity->firstName = '';
        $reference = new Reference();
        $reference->value = '';
        $entity->childA = $reference;

        $this->metadata->addPropertyConstraint('firstName', new NotBlank(['groups' => 'group1']));
        $this->metadata->addPropertyConstraint('childA', new Valid(['groups' => 'group1']));
        $this->referenceMetadata->addPropertyConstraint('value', new NotBlank());

        $violations = $this->validator->validate($entity, null, []);

        $this->assertCount(0, $violations);
    }

    public function testNestedObjectIsValidatedIfGroupInValidConstraintIsValidated()
    {
        $entity = new Entity();
        $entity->firstName = '';
        $reference = new Reference();
        $reference->value = '';
        $entity->childA = $reference;

        $this->metadata->addPropertyConstraint('firstName', new NotBlank(['groups' => 'group1']));
        $this->metadata->addPropertyConstraint('childA', new Valid(['groups' => 'group1']));
        $this->referenceMetadata->addPropertyConstraint('value', new NotBlank(['groups' => 'group1']));

        $violations = $this->validator->validate($entity, null, ['Default', 'group1']);

        $this->assertCount(2, $violations);
    }

    public function testNestedObjectIsValidatedInMultipleGroupsIfGroupInValidConstraintIsValidated()
    {
        $entity = new Entity();
        $entity->firstName = null;

        $reference = new Reference();
        $reference->value = null;

        $entity->childA = $reference;

        $this->metadata->addPropertyConstraint('firstName', new NotBlank());
        $this->metadata->addPropertyConstraint('childA', new Valid(['groups' => ['group1', 'group2']]));

        $this->referenceMetadata->addPropertyConstraint('value', new NotBlank(['groups' => 'group1']));
        $this->referenceMetadata->addPropertyConstraint('value', new NotNull(['groups' => 'group2']));

        $violations = $this->validator->validate($entity, null, ['Default', 'group1', 'group2']);

        $this->assertCount(3, $violations);
    }

    protected function createValidator(MetadataFactoryInterface $metadataFactory, array $objectInitializers = []): ValidatorInterface
    {
        $translator = new IdentityTranslator();
        $translator->setLocale('en');

        $contextFactory = new ExecutionContextFactory($translator);
        $validatorFactory = new ConstraintValidatorFactory();

        return new RecursiveValidator($contextFactory, $metadataFactory, $validatorFactory, $objectInitializers);
    }

    public function testEmptyGroupsArrayDoesNotTriggerDeprecation()
    {
        $entity = new Entity();
        $childA = new ChildA();
        $childB = new ChildB();
        $childA->name = false;
        $childB->name = 'fake';
        $entity->childA = [$childA];
        $entity->childB = [$childB];
        $validatorContext = $this->createMock(ContextualValidatorInterface::class);
        $validatorContext
            ->expects($this->once())
            ->method('validate')
            ->with($entity, null, [])
            ->willReturnSelf();

        $validator = $this
            ->getMockBuilder(RecursiveValidator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['startContext'])
            ->getMock();
        $validator
            ->expects($this->once())
            ->method('startContext')
            ->willReturn($validatorContext);

        $validator->validate($entity, null, []);
    }

    public function testRelationBetweenChildAAndChildB()
    {
        $entity = new Entity();
        $childA = new ChildA();
        $childB = new ChildB();

        $childA->childB = $childB;
        $childB->childA = $childA;

        $childA->name = false;
        $childB->name = 'fake';
        $entity->childA = [$childA];
        $entity->childB = [$childB];

        $validatorContext = $this->createMock(ContextualValidatorInterface::class);
        $validatorContext
            ->expects($this->once())
            ->method('validate')
            ->with($entity, null, [])
            ->willReturnSelf();

        $validator = $this
            ->getMockBuilder(RecursiveValidator::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['startContext'])
            ->getMock();
        $validator
            ->expects($this->once())
            ->method('startContext')
            ->willReturn($validatorContext);

        $validator->validate($entity, null, []);
    }

    public function testCollectionConstraintValidateAllGroupsForNestedConstraints()
    {
        $this->metadata->addPropertyConstraint('data', new Collection(['fields' => [
            'one' => [new NotBlank(['groups' => 'one']), new Length(['min' => 2, 'groups' => 'two'])],
            'two' => [new NotBlank(['groups' => 'two'])],
        ]]));

        $entity = new Entity();
        $entity->data = ['one' => 't', 'two' => ''];

        $violations = $this->validator->validate($entity, null, ['one', 'two']);

        $this->assertCount(2, $violations);
        $this->assertInstanceOf(Length::class, $violations->get(0)->getConstraint());
        $this->assertInstanceOf(NotBlank::class, $violations->get(1)->getConstraint());
    }

    public function testGroupedMethodConstraintValidateInSequence()
    {
        $metadata = new ClassMetadata(EntityWithGroupedConstraintOnMethods::class);
        $metadata->addPropertyConstraint('bar', new NotNull(['groups' => 'Foo']));
        $metadata->addGetterMethodConstraint('validInFoo', 'isValidInFoo', new IsTrue(['groups' => 'Foo']));
        $metadata->addGetterMethodConstraint('bar', 'getBar', new NotNull(['groups' => 'Bar']));

        $this->metadataFactory->addMetadata($metadata);

        $entity = new EntityWithGroupedConstraintOnMethods();
        $groups = new GroupSequence(['EntityWithGroupedConstraintOnMethods', 'Foo', 'Bar']);

        $violations = $this->validator->validate($entity, null, $groups);

        $this->assertCount(2, $violations);
        $this->assertInstanceOf(NotNull::class, $violations->get(0)->getConstraint());
        $this->assertInstanceOf(IsTrue::class, $violations->get(1)->getConstraint());
    }

    public function testValidConstraintOnGetterReturningNull()
    {
        $metadata = new ClassMetadata(EntityParent::class);
        $metadata->addGetterConstraint('child', new Valid());

        $this->metadataFactory->addMetadata($metadata);

        $violations = $this->validator->validate(new EntityParent());

        $this->assertCount(0, $violations);
    }

    public function testNotNullConstraintOnGetterReturningNull()
    {
        $metadata = new ClassMetadata(EntityParent::class);
        $metadata->addGetterConstraint('child', new NotNull());

        $this->metadataFactory->addMetadata($metadata);

        $violations = $this->validator->validate(new EntityParent());

        $this->assertCount(1, $violations);
        $this->assertInstanceOf(NotNull::class, $violations->get(0)->getConstraint());
    }

    public function testAllConstraintValidateAllGroupsForNestedConstraints()
    {
        $this->metadata->addPropertyConstraint('data', new All(['constraints' => [
            new NotBlank(['groups' => 'one']),
            new Length(['min' => 2, 'groups' => 'two']),
        ]]));

        $entity = new Entity();
        $entity->data = ['one' => 't', 'two' => ''];

        $violations = $this->validator->validate($entity, null, ['one', 'two']);

        $this->assertCount(3, $violations);
        $this->assertInstanceOf(NotBlank::class, $violations->get(0)->getConstraint());
        $this->assertInstanceOf(Length::class, $violations->get(1)->getConstraint());
        $this->assertInstanceOf(Length::class, $violations->get(2)->getConstraint());
    }

    public function testRequiredConstraintIsIgnored()
    {
        $violations = $this->validator->validate([], new Required());

        $this->assertCount(0, $violations);
    }

    public function testOptionalConstraintIsIgnored()
    {
        $violations = $this->validator->validate([], new Optional());

        $this->assertCount(0, $violations);
    }

    public function testValidateDoNotCascadeNestedObjectsAndArraysByDefault()
    {
        $this->metadataFactory->addMetadata(new ClassMetadata(CascadingEntity::class));
        $this->metadataFactory->addMetadata((new ClassMetadata(CascadedChild::class))
            ->addPropertyConstraint('name', new NotNull())
        );

        $entity = new CascadingEntity();
        $entity->requiredChild = new CascadedChild();
        $entity->optionalChild = new CascadedChild();
        $entity->children[] = new CascadedChild();
        CascadingEntity::$staticChild = new CascadedChild();

        $violations = $this->validator->validate($entity);

        $this->assertCount(0, $violations);

        CascadingEntity::$staticChild = null;
    }

    public function testValidateTraverseNestedArrayByDefaultIfConstrainedWithoutCascading()
    {
        $this->metadataFactory->addMetadata((new ClassMetadata(CascadingEntity::class))
            ->addPropertyConstraint('children', new All([
                new Type(CascadedChild::class),
            ]))
        );
        $this->metadataFactory->addMetadata((new ClassMetadata(CascadedChild::class))
            ->addPropertyConstraint('name', new NotNull())
        );

        $entity = new CascadingEntity();
        $entity->children[] = new \stdClass();
        $entity->children[] = new CascadedChild();

        $violations = $this->validator->validate($entity);

        $this->assertCount(1, $violations);
        $this->assertInstanceOf(Type::class, $violations->get(0)->getConstraint());
    }

    public function testValidateCascadeWithValid()
    {
        $this->metadataFactory->addMetadata((new ClassMetadata(CascadingEntity::class))
            ->addPropertyConstraint('requiredChild', new Valid())
            ->addPropertyConstraint('optionalChild', new Valid())
            ->addPropertyConstraint('staticChild', new Valid())
            ->addPropertyConstraint('children', new Valid())
        );
        $this->metadataFactory->addMetadata((new ClassMetadata(CascadedChild::class))
            ->addPropertyConstraint('name', new NotNull())
        );

        $entity = new CascadingEntity();
        $entity->requiredChild = new CascadedChild();
        $entity->children[] = new CascadedChild();
        $entity->children[] = null;
        CascadingEntity::$staticChild = new CascadedChild();

        $violations = $this->validator->validate($entity);

        $this->assertCount(3, $violations);
        $this->assertInstanceOf(NotNull::class, $violations->get(0)->getConstraint());
        $this->assertInstanceOf(NotNull::class, $violations->get(1)->getConstraint());
        $this->assertInstanceOf(NotNull::class, $violations->get(2)->getConstraint());
        $this->assertSame('requiredChild.name', $violations->get(0)->getPropertyPath());
        $this->assertSame('staticChild.name', $violations->get(1)->getPropertyPath());
        $this->assertSame('children[0].name', $violations->get(2)->getPropertyPath());

        CascadingEntity::$staticChild = null;
    }

    public function testValidateWithExplicitCascade()
    {
        $this->metadataFactory->addMetadata((new ClassMetadata(CascadingEntity::class))
            ->addConstraint(new Cascade())
        );
        $this->metadataFactory->addMetadata((new ClassMetadata(CascadedChild::class))
            ->addPropertyConstraint('name', new NotNull())
        );

        $entity = new CascadingEntity();
        $entity->requiredChild = new CascadedChild();
        $entity->children[] = new CascadedChild();
        $entity->children[] = null;
        CascadingEntity::$staticChild = new CascadedChild();

        $violations = $this->validator->validate($entity);

        $this->assertCount(3, $violations);
        $this->assertInstanceOf(NotNull::class, $violations->get(0)->getConstraint());
        $this->assertInstanceOf(NotNull::class, $violations->get(1)->getConstraint());
        $this->assertInstanceOf(NotNull::class, $violations->get(2)->getConstraint());
        $this->assertSame('requiredChild.name', $violations->get(0)->getPropertyPath());
        $this->assertSame('staticChild.name', $violations->get(1)->getPropertyPath());
        $this->assertSame('children[0].name', $violations->get(2)->getPropertyPath());

        CascadingEntity::$staticChild = null;
    }

    public function testValidatedConstraintsHashesDoNotCollide()
    {
        $metadata = new ClassMetadata(Entity::class);
        $metadata->addPropertyConstraint('initialized', new NotNull(['groups' => 'should_pass']));
        $metadata->addPropertyConstraint('initialized', new IsNull(['groups' => 'should_fail']));

        $this->metadataFactory->addMetadata($metadata);

        $entity = new Entity();
        $entity->data = new \stdClass();

        $this->assertCount(2, $this->validator->validate($entity, new TestConstraintHashesDoNotCollide()));
    }

    public function testValidatedConstraintsHashesDoNotCollideWithSameConstraintValidatingDifferentProperties()
    {
        $value = new \stdClass();

        $entity = new Entity();
        $entity->firstName = $value;
        $entity->setLastName($value);

        $validator = $this->validator->startContext($entity);

        $constraint = new IsNull();
        $validator->atPath('firstName')
            ->validate($entity->firstName, $constraint);
        $validator->atPath('lastName')
            ->validate($entity->getLastName(), $constraint);

        $this->assertCount(2, $validator->getViolations());
    }
}

final class TestConstraintHashesDoNotCollide extends Constraint
{
}

final class TestConstraintHashesDoNotCollideValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (!$value instanceof Entity) {
            throw new \LogicException();
        }

        $this->context->getValidator()
            ->inContext($this->context)
            ->atPath('data')
            ->validate($value, new NotNull())
            ->validate($value, new NotNull())
            ->validate($value, new IsFalse());

        $this->context->getValidator()
            ->inContext($this->context)
            ->validate($value, null, new GroupSequence(['should_pass']))
            ->validate($value, null, new GroupSequence(['should_fail']));
    }
}
