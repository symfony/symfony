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
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ExecutionContextInterface;
use Symfony\Component\Validator\MetadataFactoryInterface;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\Reference;
use Symfony\Component\Validator\ValidatorInterface as LegacyValidatorInterface;

/**
 * Verifies that a validator satisfies the API of Symfony < 2.5.
 *
 * @since  2.5
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractLegacyApiTest extends AbstractValidatorTest
{
    /**
     * @var LegacyValidatorInterface
     */
    protected $validator;

    /**
     * @param MetadataFactoryInterface $metadataFactory
     *
     * @return LegacyValidatorInterface
     */
    abstract protected function createValidator(MetadataFactoryInterface $metadataFactory, array $objectInitializers = array());

    protected function setUp()
    {
        parent::setUp();

        $this->validator = $this->createValidator($this->metadataFactory);
    }

    protected function validate($value, $constraints = null, $groups = null)
    {
        if (null === $constraints) {
            $constraints = new Valid();
        }

        if ($constraints instanceof Valid) {
            return $this->validator->validate($value, $groups, $constraints->traverse, $constraints->deep);
        }

        return $this->validator->validateValue($value, $constraints, $groups);
    }

    protected function validateProperty($object, $propertyName, $groups = null)
    {
        return $this->validator->validateProperty($object, $propertyName, $groups);
    }

    protected function validatePropertyValue($object, $propertyName, $value, $groups = null)
    {
        return $this->validator->validatePropertyValue($object, $propertyName, $value, $groups);
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\NoSuchMetadataException
     */
    public function testTraversableTraverseDisabled()
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator(array('key' => $entity));

        $callback = function () {
            $this->fail('Should not be called');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $this->validator->validate($traversable, 'Group');
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\NoSuchMetadataException
     */
    public function testRecursiveTraversableRecursiveTraversalDisabled()
    {
        $entity = new Entity();
        $traversable = new \ArrayIterator(array(
            2 => new \ArrayIterator(array('key' => $entity)),
        ));

        $callback = function () {
            $this->fail('Should not be called');
        };

        $this->metadata->addConstraint(new Callback(array(
            'callback' => $callback,
            'groups' => 'Group',
        )));

        $this->validator->validate($traversable, 'Group');
    }

    public function testValidateInContext()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $previousValue = $context->getValue();
            $previousMetadata = $context->getMetadata();
            $previousPath = $context->getPropertyPath();
            $previousGroup = $context->getGroup();

            $context->validate($value->reference, 'subpath');

            // context changes shouldn't leak out of the validate() call
            $this->assertSame($previousValue, $context->getValue());
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
            $this->assertSame($this->metadataFactory, $context->getMetadataFactory());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference, $context->getValue());
            $this->assertSame($entity->reference, $value);

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

        $violations = $this->validator->validate($entity, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
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
        $entity = new Entity();
        $entity->reference = new Reference();

        $callback1 = function ($value, ExecutionContextInterface $context) {
            $previousValue = $context->getValue();
            $previousMetadata = $context->getMetadata();
            $previousPath = $context->getPropertyPath();
            $previousGroup = $context->getGroup();

            $context->validate(array('key' => $value->reference), 'subpath');

            // context changes shouldn't leak out of the validate() call
            $this->assertSame($previousValue, $context->getValue());
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
            $this->assertSame($this->metadataFactory, $context->getMetadataFactory());
            $this->assertSame($entity, $context->getRoot());
            $this->assertSame($entity->reference, $context->getValue());
            $this->assertSame($entity->reference, $value);

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

        $violations = $this->validator->validate($entity, 'Group');

        /* @var ConstraintViolationInterface[] $violations */
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

    public function testAddCustomizedViolation()
    {
        $entity = new Entity();

        $callback = function ($value, ExecutionContextInterface $context) {
            $context->addViolation(
                'Message %param%',
                array('%param%' => 'value'),
                'Invalid value',
                2,
                'Code'
            );
        };

        $this->metadata->addConstraint(new Callback($callback));

        $violations = $this->validator->validate($entity);

        /* @var ConstraintViolationInterface[] $violations */
        $this->assertCount(1, $violations);
        $this->assertSame('Message value', $violations[0]->getMessage());
        $this->assertSame('Message %param%', $violations[0]->getMessageTemplate());
        $this->assertSame(array('%param%' => 'value'), $violations[0]->getParameters());
        $this->assertSame('', $violations[0]->getPropertyPath());
        $this->assertSame($entity, $violations[0]->getRoot());
        $this->assertSame('Invalid value', $violations[0]->getInvalidValue());
        $this->assertSame(2, $violations[0]->getPlural());
        $this->assertSame('Code', $violations[0]->getCode());
    }

    public function testInitializeObjectsOnFirstValidation()
    {
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
        $callback = function ($object, ExecutionContextInterface $context) {
            $this->assertTrue($object->initialized);

            // validate again in same group
            $context->validate($object);

            // validate again in other group
            $context->validate($object, '', 'SomeGroup');
        };

        $this->metadata->addConstraint(new Callback($callback));

        $this->validate($entity);

        $this->assertTrue($entity->initialized);
    }

    public function testGetMetadataFactory()
    {
        $this->assertSame($this->metadataFactory, $this->validator->getMetadataFactory());
    }
}
