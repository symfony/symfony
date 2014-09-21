<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests;

use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\ExecutionContextInterface;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Tests\Fixtures\Reference;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Tests\Fixtures\FailingConstraint;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintAValidator;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ValidationVisitor;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ValidationVisitorTest extends \PHPUnit_Framework_TestCase
{
    const CLASS_NAME = 'Symfony\Component\Validator\Tests\Fixtures\Entity';

    /**
     * @var ValidationVisitor
     */
    private $visitor;

    /**
     * @var FakeMetadataFactory
     */
    private $metadataFactory;

    /**
     * @var ClassMetadata
     */
    private $metadata;

    protected function setUp()
    {
        $this->metadataFactory = new FakeMetadataFactory();
        $this->visitor = new ValidationVisitor('Root', $this->metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
        $this->metadata = new ClassMetadata(self::CLASS_NAME);
        $this->metadataFactory->addMetadata($this->metadata);
    }

    protected function tearDown()
    {
        $this->metadataFactory = null;
        $this->visitor = null;
        $this->metadata = null;
    }

    public function testValidatePassesCorrectClassAndProperty()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $entity = new Entity();
        $this->visitor->validate($entity, 'Default', '');

        $context = ConstraintAValidator::$passedContext;

        $this->assertEquals('Symfony\Component\Validator\Tests\Fixtures\Entity', $context->getClassName());
        $this->assertNull($context->getPropertyName());
    }

    public function testValidateConstraints()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $this->visitor->validate(new Entity(), 'Default', '');

        $this->assertCount(1, $this->visitor->getViolations());
    }

    public function testValidateTwiceValidatesConstraintsOnce()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $entity = new Entity();

        $this->visitor->validate($entity, 'Default', '');
        $this->visitor->validate($entity, 'Default', '');

        $this->assertCount(1, $this->visitor->getViolations());
    }

    public function testValidateDifferentObjectsValidatesTwice()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $this->visitor->validate(new Entity(), 'Default', '');
        $this->visitor->validate(new Entity(), 'Default', '');

        $this->assertCount(2, $this->visitor->getViolations());
    }

    public function testValidateTwiceInDifferentGroupsValidatesTwice()
    {
        $this->metadata->addConstraint(new ConstraintA());
        $this->metadata->addConstraint(new ConstraintA(array('groups' => 'Custom')));

        $entity = new Entity();

        $this->visitor->validate($entity, 'Default', '');
        $this->visitor->validate($entity, 'Custom', '');

        $this->assertCount(2, $this->visitor->getViolations());
    }

    public function testValidatePropertyConstraints()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->visitor->validate(new Entity(), 'Default', '');

        $this->assertCount(1, $this->visitor->getViolations());
    }

    public function testValidateGetterConstraints()
    {
        $this->metadata->addGetterConstraint('lastName', new ConstraintA());

        $this->visitor->validate(new Entity(), 'Default', '');

        $this->assertCount(1, $this->visitor->getViolations());
    }

    public function testValidateInDefaultGroupTraversesGroupSequence()
    {
        $entity = new Entity();

        $this->metadata->addPropertyConstraint('firstName', new FailingConstraint(array(
            'groups' => 'First',
        )));
        $this->metadata->addGetterConstraint('lastName', new FailingConstraint(array(
            'groups' => 'Default',
        )));
        $this->metadata->setGroupSequence(array('First', $this->metadata->getDefaultGroup()));

        $this->visitor->validate($entity, 'Default', '');

        // After validation of group "First" failed, no more group was
        // validated
        $violations = new ConstraintViolationList(array(
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                'firstName',
                ''
            ),
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    public function testValidateInGroupSequencePropagatesDefaultGroup()
    {
        $entity = new Entity();
        $entity->reference = new Reference();

        $this->metadata->addPropertyConstraint('reference', new Valid());
        $this->metadata->setGroupSequence(array($this->metadata->getDefaultGroup()));

        $referenceMetadata = new ClassMetadata(get_class($entity->reference));
        $referenceMetadata->addConstraint(new FailingConstraint(array(
                // this constraint is only evaluated if group "Default" is
                // propagated to the reference
                'groups' => 'Default',
            )));
        $this->metadataFactory->addMetadata($referenceMetadata);

        $this->visitor->validate($entity, 'Default', '');

        // The validation of the reference's FailingConstraint in group
        // "Default" was launched
        $violations = new ConstraintViolationList(array(
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                'reference',
                $entity->reference
            ),
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    public function testValidateInOtherGroupTraversesNoGroupSequence()
    {
        $entity = new Entity();

        $this->metadata->addPropertyConstraint('firstName', new FailingConstraint(array(
            'groups' => 'First',
        )));
        $this->metadata->addGetterConstraint('lastName', new FailingConstraint(array(
            'groups' => $this->metadata->getDefaultGroup(),
        )));
        $this->metadata->setGroupSequence(array('First', $this->metadata->getDefaultGroup()));

        $this->visitor->validate($entity, $this->metadata->getDefaultGroup(), '');

        // Only group "Second" was validated
        $violations = new ConstraintViolationList(array(
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                'lastName',
                ''
            ),
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    public function testValidateCascadedPropertyValidatesReferences()
    {
        $entity = new Entity();
        $entity->reference = new Entity();

        // add a constraint for the entity that always fails
        $this->metadata->addConstraint(new FailingConstraint());

        // validate entity when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        // invoke validation on an object
        $this->visitor->validate($entity, 'Default', '');

        $violations = new ConstraintViolationList(array(
            // generated by the root object
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                '',
                $entity
            ),
            // generated by the reference
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                'reference',
                $entity->reference
            ),
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    public function testValidateCascadedPropertyValidatesArraysByDefault()
    {
        $entity = new Entity();
        $entity->reference = array('key' => new Entity());

        // add a constraint for the entity that always fails
        $this->metadata->addConstraint(new FailingConstraint());

        // validate array when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->visitor->validate($entity, 'Default', '');

        $violations = new ConstraintViolationList(array(
            // generated by the root object
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                '',
                $entity
            ),
            // generated by the reference
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                'reference[key]',
                $entity->reference['key']
            ),
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    public function testValidateCascadedPropertyValidatesTraversableByDefault()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Entity()));

        // add a constraint for the entity that always fails
        $this->metadata->addConstraint(new FailingConstraint());

        // validate array when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->visitor->validate($entity, 'Default', '');

        $violations = new ConstraintViolationList(array(
            // generated by the root object
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                '',
                $entity
            ),
            // generated by the reference
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                'reference[key]',
                $entity->reference['key']
            ),
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    public function testValidateCascadedPropertyDoesNotValidateTraversableIfDisabled()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array('key' => new Entity()));

        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));

        // add a constraint for the entity that always fails
        $this->metadata->addConstraint(new FailingConstraint());

        // validate array when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => false,
        )));

        $this->visitor->validate($entity, 'Default', '');

        $violations = new ConstraintViolationList(array(
            // generated by the root object
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                '',
                $entity
            ),
            // nothing generated by the reference!
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    public function testMetadataMayNotExistIfTraversalIsEnabled()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator();

        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => true,
        )));

        $this->visitor->validate($entity, 'Default', '');
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

        $this->visitor->validate($entity, 'Default', '');
    }

    public function testValidateCascadedPropertyDoesNotRecurseByDefault()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array(
            // The inner iterator should not be traversed by default
            'key' => new \ArrayIterator(array(
                'nested' => new Entity(),
            )),
        ));

        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));

        // add a constraint for the entity that always fails
        $this->metadata->addConstraint(new FailingConstraint());

        // validate iterator when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->visitor->validate($entity, 'Default', '');

        $violations = new ConstraintViolationList(array(
            // generated by the root object
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                '',
                $entity
            ),
            // nothing generated by the reference!
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    // https://github.com/symfony/symfony/issues/6246
    public function testValidateCascadedPropertyRecursesArraysByDefault()
    {
        $entity = new Entity();
        $entity->reference = array(
            'key' => array(
                'nested' => new Entity(),
            ),
        );

        // add a constraint for the entity that always fails
        $this->metadata->addConstraint(new FailingConstraint());

        // validate iterator when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->visitor->validate($entity, 'Default', '');

        $violations = new ConstraintViolationList(array(
            // generated by the root object
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                '',
                $entity
            ),
            // nothing generated by the reference!
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                'reference[key][nested]',
                $entity->reference['key']['nested']
            ),
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    public function testValidateCascadedPropertyRecursesIfDeepIsSet()
    {
        $entity = new Entity();
        $entity->reference = new \ArrayIterator(array(
            // The inner iterator should now be traversed
            'key' => new \ArrayIterator(array(
                'nested' => new Entity(),
            )),
        ));

        // add a constraint for the entity that always fails
        $this->metadata->addConstraint(new FailingConstraint());

        // validate iterator when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'deep' => true,
        )));

        $this->visitor->validate($entity, 'Default', '');

        $violations = new ConstraintViolationList(array(
            // generated by the root object
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                '',
                $entity
            ),
            // nothing generated by the reference!
            new ConstraintViolation(
                'Failed',
                'Failed',
                array(),
                'Root',
                'reference[key][nested]',
                $entity->reference['key']['nested']
            ),
        ));

        $this->assertEquals($violations, $this->visitor->getViolations());
    }

    public function testValidateCascadedPropertyDoesNotValidateNestedScalarValues()
    {
        $entity = new Entity();
        $entity->reference = array('scalar', 'values');

        // validate array when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->visitor->validate($entity, 'Default', '');

        $this->assertCount(0, $this->visitor->getViolations());
    }

    public function testValidateCascadedPropertyDoesNotValidateNullValues()
    {
        $entity = new Entity();
        $entity->reference = null;

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->visitor->validate($entity, 'Default', '');

        $this->assertCount(0, $this->visitor->getViolations());
    }

    /**
     * @expectedException \Symfony\Component\Validator\Exception\NoSuchMetadataException
     */
    public function testValidateCascadedPropertyRequiresObjectOrArray()
    {
        $entity = new Entity();
        $entity->reference = 'no object';

        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->visitor->validate($entity, 'Default', '');
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

        $this->visitor = new ValidationVisitor('Root', $this->metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator(), null, array(
            $initializer1,
            $initializer2,
        ));

        // prepare constraint which
        // * checks that "initialized" is set to true
        // * validates the object again
        $callback = function ($object, ExecutionContextInterface $context) use ($test) {
            $test->assertTrue($object->initialized);

            // validate again in same group
            $context->validate($object);

            // validate again in other group
            $context->validate($object, '', 'SomeGroup');
        };

        $this->metadata->addConstraint(new Callback(array($callback)));

        $this->visitor->validate($entity, 'Default', '');

        $this->assertTrue($entity->initialized);
    }
}
