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

use Symfony\Component\Validator\Tests\Fixtures\ConstraintAValidator;
use Symfony\Component\Validator\DefaultTranslator;
use Symfony\Component\Validator\ValidationVisitor;
use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\Reference;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\FailingConstraint;
use Symfony\Component\Validator\GraphWalker;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Constraints\Collection;

class GraphWalkerTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Component\Validator\Tests\Fixtures\Entity';

    /**
     * @var ValidationVisitor
     */
    private $visitor;

    /**
     * @var FakeMetadataFactory
     */
    protected $metadataFactory;

    /**
     * @var GraphWalker
     */
    protected $walker;

    /**
     * @var ClassMetadata
     */
    protected $metadata;

    protected function setUp()
    {
        set_error_handler(array($this, "deprecationErrorHandler"));

        $this->metadataFactory = new FakeMetadataFactory();
        $this->visitor = new ValidationVisitor('Root', $this->metadataFactory, new ConstraintValidatorFactory(), new DefaultTranslator());
        $this->walker = $this->visitor->getGraphWalker();
        $this->metadata = new ClassMetadata(self::CLASSNAME);
        $this->metadataFactory->addMetadata($this->metadata);
    }

    protected function tearDown()
    {
        restore_error_handler();

        $this->metadataFactory = null;
        $this->visitor = null;
        $this->walker = null;
        $this->metadata = null;
    }

    public function deprecationErrorHandler($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return \PHPUnit_Util_ErrorHandler::handleError($errorNumber, $message, $file, $line);
    }

    public function testWalkObjectPassesCorrectClassAndProperty()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $entity = new Entity();
        $this->walker->walkObject($this->metadata, $entity, 'Default', '');

        $context = ConstraintAValidator::$passedContext;

        $this->assertEquals('Symfony\Component\Validator\Tests\Fixtures\Entity', $context->getCurrentClass());
        $this->assertNull($context->getCurrentProperty());
    }

    public function testWalkObjectValidatesConstraints()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $this->walker->walkObject($this->metadata, new Entity(), 'Default', '');

        $this->assertCount(1, $this->walker->getViolations());
    }

    public function testWalkObjectTwiceValidatesConstraintsOnce()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $entity = new Entity();

        $this->walker->walkObject($this->metadata, $entity, 'Default', '');
        $this->walker->walkObject($this->metadata, $entity, 'Default', '');

        $this->assertCount(1, $this->walker->getViolations());
    }

    public function testWalkObjectOnceInVisitorAndOnceInWalkerValidatesConstraintsOnce()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $entity = new Entity();

        $this->visitor->validate($entity, 'Default', '');
        $this->walker->walkObject($this->metadata, $entity, 'Default', '');

        $this->assertCount(1, $this->walker->getViolations());
    }

    public function testWalkDifferentObjectsValidatesTwice()
    {
        $this->metadata->addConstraint(new ConstraintA());

        $this->walker->walkObject($this->metadata, new Entity(), 'Default', '');
        $this->walker->walkObject($this->metadata, new Entity(), 'Default', '');

        $this->assertCount(2, $this->walker->getViolations());
    }

    public function testWalkObjectTwiceInDifferentGroupsValidatesTwice()
    {
        $this->metadata->addConstraint(new ConstraintA());
        $this->metadata->addConstraint(new ConstraintA(array('groups' => 'Custom')));

        $entity = new Entity();

        $this->walker->walkObject($this->metadata, $entity, 'Default', '');
        $this->walker->walkObject($this->metadata, $entity, 'Custom', '');

        $this->assertCount(2, $this->walker->getViolations());
    }

    public function testWalkObjectValidatesPropertyConstraints()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->walker->walkObject($this->metadata, new Entity(), 'Default', '');

        $this->assertCount(1, $this->walker->getViolations());
    }

    public function testWalkObjectValidatesGetterConstraints()
    {
        $this->metadata->addGetterConstraint('lastName', new ConstraintA());

        $this->walker->walkObject($this->metadata, new Entity(), 'Default', '');

        $this->assertCount(1, $this->walker->getViolations());
    }

    public function testWalkObjectInDefaultGroupTraversesGroupSequence()
    {
        $entity = new Entity();

        $this->metadata->addPropertyConstraint('firstName', new FailingConstraint(array(
            'groups' => 'First',
        )));
        $this->metadata->addGetterConstraint('lastName', new FailingConstraint(array(
            'groups' => 'Default',
        )));
        $this->metadata->setGroupSequence(array('First', $this->metadata->getDefaultGroup()));

        $this->walker->walkObject($this->metadata, $entity, 'Default', '');

        // After validation of group "First" failed, no more group was
        // validated
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            'Root',
            'firstName',
            ''
        ));

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkObjectInGroupSequencePropagatesDefaultGroup()
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

        $this->walker->walkObject($this->metadata, $entity, 'Default', '');

        // The validation of the reference's FailingConstraint in group
        // "Default" was launched
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            'Root',
            'reference',
            $entity->reference
        ));

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkObjectInOtherGroupTraversesNoGroupSequence()
    {
        $entity = new Entity();

        $this->metadata->addPropertyConstraint('firstName', new FailingConstraint(array(
            'groups' => 'First',
        )));
        $this->metadata->addGetterConstraint('lastName', new FailingConstraint(array(
            'groups' => $this->metadata->getDefaultGroup(),
        )));
        $this->metadata->setGroupSequence(array('First', $this->metadata->getDefaultGroup()));

        $this->walker->walkObject($this->metadata, $entity, $this->metadata->getDefaultGroup(), '');

        // Only group "Second" was validated
        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            'Root',
            'lastName',
            ''
        ));

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkPropertyPassesCorrectClassAndProperty()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->walker->walkPropertyValue($this->metadata, 'firstName', 'value', 'Default', '');

        $context = ConstraintAValidator::$passedContext;

        $this->assertEquals('Symfony\Component\Validator\Tests\Fixtures\Entity', $context->getCurrentClass());
        $this->assertEquals('firstName', $context->getCurrentProperty());
    }

    public function testWalkPropertyValueValidatesConstraints()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->walker->walkPropertyValue($this->metadata, 'firstName', 'value', 'Default', '');

        $this->assertCount(1, $this->walker->getViolations());
    }

    public function testWalkCascadedPropertyValidatesReferences()
    {
        $entity = new Entity();
        $entityMetadata = new ClassMetadata(get_class($entity));
        $this->metadataFactory->addMetadata($entityMetadata);

        // add a constraint for the entity that always fails
        $entityMetadata->addConstraint(new FailingConstraint());

        // validate entity when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        // invoke validation on an object
        $this->walker->walkPropertyValue(
            $this->metadata,
            'reference',
            $entity,  // object!
            'Default',
            'path'
        );

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            'Root',
            'path',
            $entity
        ));

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkCascadedPropertyValidatesArraysByDefault()
    {
        $entity = new Entity();
        $entityMetadata = new ClassMetadata(get_class($entity));
        $this->metadataFactory->addMetadata($entityMetadata);

        // add a constraint for the entity that always fails
        $entityMetadata->addConstraint(new FailingConstraint());

        // validate array when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->walker->walkPropertyValue(
            $this->metadata,
            'reference',
            array('key' => $entity), // array!
            'Default',
            'path'
        );

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            'Root',
            'path[key]',
            $entity
        ));

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkCascadedPropertyValidatesTraversableByDefault()
    {
        $entity = new Entity();
        $entityMetadata = new ClassMetadata(get_class($entity));
        $this->metadataFactory->addMetadata($entityMetadata);
        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));

        // add a constraint for the entity that always fails
        $entityMetadata->addConstraint(new FailingConstraint());

        // validate array when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->walker->walkPropertyValue(
            $this->metadata,
            'reference',
            new \ArrayIterator(array('key' => $entity)),
            'Default',
            'path'
        );

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            'Root',
            'path[key]',
            $entity
        ));

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkCascadedPropertyDoesNotValidateTraversableIfDisabled()
    {
        $entity = new Entity();
        $entityMetadata = new ClassMetadata(get_class($entity));
        $this->metadataFactory->addMetadata($entityMetadata);
        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));

        // add a constraint for the entity that always fails
        $entityMetadata->addConstraint(new FailingConstraint());

        // validate array when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'traverse' => false,
        )));

        $this->walker->walkPropertyValue(
            $this->metadata,
            'reference',
            new \ArrayIterator(array('key' => $entity)),
            'Default',
            'path'
        );

        $violations = new ConstraintViolationList();

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkCascadedPropertyDoesNotRecurseByDefault()
    {
        $entity = new Entity();
        $entityMetadata = new ClassMetadata(get_class($entity));
        $this->metadataFactory->addMetadata($entityMetadata);
        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));

        // add a constraint for the entity that always fails
        $entityMetadata->addConstraint(new FailingConstraint());

        // validate iterator when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->walker->walkPropertyValue(
            $this->metadata,
            'reference',
            new \ArrayIterator(array(
                // The inner iterator should not be traversed by default
                'key' => new \ArrayIterator(array(
                    'nested' => $entity,
                )),
            )),
            'Default',
            'path'
        );

        $violations = new ConstraintViolationList();

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkCascadedPropertyRecursesIfDeepIsSet()
    {
        $entity = new Entity();
        $entityMetadata = new ClassMetadata(get_class($entity));
        $this->metadataFactory->addMetadata($entityMetadata);
        $this->metadataFactory->addMetadata(new ClassMetadata('ArrayIterator'));

        // add a constraint for the entity that always fails
        $entityMetadata->addConstraint(new FailingConstraint());

        // validate iterator when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid(array(
            'deep' => true,
        )));

        $this->walker->walkPropertyValue(
            $this->metadata,
            'reference',
            new \ArrayIterator(array(
                // The inner iterator should now be traversed
                'key' => new \ArrayIterator(array(
                    'nested' => $entity,
                )),
            )),
            'Default',
            'path'
        );

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'Failed',
            'Failed',
            array(),
            'Root',
            'path[key][nested]',
            $entity
        ));

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkCascadedPropertyDoesNotValidateNestedScalarValues()
    {
        // validate array when validating the property "reference"
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->walker->walkPropertyValue(
            $this->metadata,
            'reference',
            array('scalar', 'values'),
            'Default',
            'path'
        );

        $violations = new ConstraintViolationList();

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkCascadedPropertyDoesNotValidateNullValues()
    {
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->walker->walkPropertyValue(
            $this->metadata,
            'reference',
            null,
            'Default',
            ''
        );

        $this->assertCount(0, $this->walker->getViolations());
    }

    public function testWalkCascadedPropertyRequiresObjectOrArray()
    {
        $this->metadata->addPropertyConstraint('reference', new Valid());

        $this->setExpectedException('Symfony\Component\Validator\Exception\NoSuchMetadataException');

        $this->walker->walkPropertyValue(
            $this->metadata,
            'reference',
            'no object',
            'Default',
            ''
        );
    }

    public function testWalkConstraintBuildsAViolationIfFailed()
    {
        $constraint = new ConstraintA();

        $this->walker->walkConstraint($constraint, 'foobar', 'Default', 'firstName.path');

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation(
            'message',
            'message',
            array('param' => 'value'),
            'Root',
            'firstName.path',
            'foobar'
        ));

        $this->assertEquals($violations, $this->walker->getViolations());
    }

    public function testWalkConstraintBuildsNoViolationIfSuccessful()
    {
        $constraint = new ConstraintA();

        $this->walker->walkConstraint($constraint, 'VALID', 'Default', 'firstName.path');

        $this->assertCount(0, $this->walker->getViolations());
    }

    public function testWalkObjectUsesCorrectPropertyPathInViolationsWhenUsingCollections()
    {
        $constraint = new Collection(array(
            'foo' => new ConstraintA(),
            'bar' => new ConstraintA(),
        ));

        $this->walker->walkConstraint($constraint, array('foo' => 'VALID'), 'Default', 'collection');
        $violations = $this->walker->getViolations();
        $this->assertEquals('collection[bar]', $violations[0]->getPropertyPath());
    }

    public function testWalkObjectUsesCorrectPropertyPathInViolationsWhenUsingNestedCollections()
    {
        $constraint = new Collection(array(
            'foo' => new Collection(array(
                'foo' => new ConstraintA(),
                'bar' => new ConstraintA(),
            )),
        ));

        $this->walker->walkConstraint($constraint, array('foo' => array('foo' => 'VALID')), 'Default', 'collection');
        $violations = $this->walker->getViolations();
        $this->assertEquals('collection[foo][bar]', $violations[0]->getPropertyPath());
    }

    protected function getProperty($property)
    {
        $p = new \ReflectionProperty($this->walker, $property);
        $p->setAccessible(true);

        return $p->getValue($this->walker);
    }
}
