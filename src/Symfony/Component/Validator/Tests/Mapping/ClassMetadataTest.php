<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Cascade;
use Symfony\Component\Validator\Constraints\Composite;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\GroupDefinitionException;
use Symfony\Component\Validator\Mapping\CascadingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\EntityParent;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\GroupSequenceProviderEntity;
use Symfony\Component\Validator\Tests\Fixtures\CascadingEntity;
use Symfony\Component\Validator\Tests\Fixtures\ClassConstraint;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\PropertyConstraint;

class ClassMetadataTest extends TestCase
{
    private const CLASSNAME = Entity::class;
    private const PARENTCLASS = EntityParent::class;
    private const PROVIDERCLASS = GroupSequenceProviderEntity::class;
    private const PROVIDERCHILDCLASS = 'Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderChildEntity';

    protected $metadata;

    protected function setUp(): void
    {
        $this->metadata = new ClassMetadata(self::CLASSNAME);
    }

    protected function tearDown(): void
    {
        $this->metadata = null;
    }

    public function testAddConstraintDoesNotAcceptValid()
    {
        self::expectException(ConstraintDefinitionException::class);

        $this->metadata->addConstraint(new Valid());
    }

    public function testAddConstraintRequiresClassConstraints()
    {
        self::expectException(ConstraintDefinitionException::class);

        $this->metadata->addConstraint(new PropertyConstraint());
    }

    public function testAddCompositeConstraintRejectsNestedPropertyConstraints()
    {
        self::expectException(ConstraintDefinitionException::class);
        self::expectExceptionMessage('The constraint "Symfony\Component\Validator\Tests\Fixtures\PropertyConstraint" cannot be put on classes.');

        $this->metadata->addConstraint(new ClassCompositeConstraint([new PropertyConstraint()]));
    }

    public function testAddCompositeConstraintAcceptsNestedClassConstraints()
    {
        $this->metadata->addConstraint($constraint = new ClassCompositeConstraint([new ClassConstraint()]));
        self::assertSame($this->metadata->getConstraints(), [$constraint]);
    }

    public function testAddPropertyConstraints()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());
        $this->metadata->addPropertyConstraint('lastName', new ConstraintB());

        self::assertEquals(['firstName', 'lastName'], $this->metadata->getConstrainedProperties());
    }

    public function testAddMultiplePropertyConstraints()
    {
        $this->metadata->addPropertyConstraints('lastName', [new ConstraintA(), new ConstraintB()]);

        $constraints = [
            new ConstraintA(['groups' => ['Default', 'Entity']]),
            new ConstraintB(['groups' => ['Default', 'Entity']]),
        ];

        $properties = $this->metadata->getPropertyMetadata('lastName');

        self::assertCount(1, $properties);
        self::assertEquals('lastName', $properties[0]->getName());
        self::assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testAddGetterConstraints()
    {
        $this->metadata->addGetterConstraint('lastName', new ConstraintA());
        $this->metadata->addGetterConstraint('lastName', new ConstraintB());

        $constraints = [
            new ConstraintA(['groups' => ['Default', 'Entity']]),
            new ConstraintB(['groups' => ['Default', 'Entity']]),
        ];

        $properties = $this->metadata->getPropertyMetadata('lastName');

        self::assertCount(1, $properties);
        self::assertEquals('getLastName', $properties[0]->getName());
        self::assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testAddMultipleGetterConstraints()
    {
        $this->metadata->addGetterConstraints('lastName', [new ConstraintA(), new ConstraintB()]);

        $constraints = [
            new ConstraintA(['groups' => ['Default', 'Entity']]),
            new ConstraintB(['groups' => ['Default', 'Entity']]),
        ];

        $properties = $this->metadata->getPropertyMetadata('lastName');

        self::assertCount(1, $properties);
        self::assertEquals('getLastName', $properties[0]->getName());
        self::assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testMergeConstraintsMergesClassConstraints()
    {
        $parent = new ClassMetadata(self::PARENTCLASS);
        $parent->addConstraint(new ConstraintA());

        $this->metadata->mergeConstraints($parent);
        $this->metadata->addConstraint(new ConstraintA());

        $constraints = [
            new ConstraintA(['groups' => [
                'Default',
                'EntityParent',
                'Entity',
            ]]),
            new ConstraintA(['groups' => [
                'Default',
                'Entity',
            ]]),
        ];

        self::assertEquals($constraints, $this->metadata->getConstraints());
    }

    public function testMergeConstraintsMergesMemberConstraints()
    {
        $parent = new ClassMetadata(self::PARENTCLASS);
        $parent->addPropertyConstraint('firstName', new ConstraintA());
        $parent->addPropertyConstraint('firstName', new ConstraintB(['groups' => 'foo']));

        $this->metadata->mergeConstraints($parent);
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $constraintA1 = new ConstraintA(['groups' => [
            'Default',
            'EntityParent',
            'Entity',
        ]]);
        $constraintA2 = new ConstraintA(['groups' => [
            'Default',
            'Entity',
        ]]);
        $constraintB = new ConstraintB([
            'groups' => ['foo'],
        ]);

        $constraints = [
            $constraintA1,
            $constraintB,
            $constraintA2,
        ];

        $constraintsByGroup = [
            'Default' => [
                $constraintA1,
                $constraintA2,
            ],
            'EntityParent' => [
                $constraintA1,
            ],
            'Entity' => [
                $constraintA1,
                $constraintA2,
            ],
            'foo' => [
                $constraintB,
            ],
        ];

        $members = $this->metadata->getPropertyMetadata('firstName');

        self::assertCount(1, $members);
        self::assertEquals(self::PARENTCLASS, $members[0]->getClassName());
        self::assertEquals($constraints, $members[0]->getConstraints());
        self::assertEquals($constraintsByGroup, $members[0]->constraintsByGroup);
    }

    public function testMemberMetadatas()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        self::assertTrue($this->metadata->hasPropertyMetadata('firstName'));
        self::assertFalse($this->metadata->hasPropertyMetadata('non_existent_field'));
    }

    public function testMergeConstraintsKeepsPrivateMembersSeparate()
    {
        $parent = new ClassMetadata(self::PARENTCLASS);
        $parent->addPropertyConstraint('internal', new ConstraintA());

        $this->metadata->mergeConstraints($parent);
        $this->metadata->addPropertyConstraint('internal', new ConstraintA());

        $parentConstraints = [
            new ConstraintA(['groups' => [
                'Default',
                'EntityParent',
                'Entity',
            ]]),
        ];
        $constraints = [
            new ConstraintA(['groups' => [
                'Default',
                'Entity',
            ]]),
        ];

        $members = $this->metadata->getPropertyMetadata('internal');

        self::assertCount(2, $members);
        self::assertEquals(self::PARENTCLASS, $members[0]->getClassName());
        self::assertEquals($parentConstraints, $members[0]->getConstraints());
        self::assertEquals(self::CLASSNAME, $members[1]->getClassName());
        self::assertEquals($constraints, $members[1]->getConstraints());
    }

    public function testGetReflectionClass()
    {
        $reflClass = new \ReflectionClass(self::CLASSNAME);

        self::assertEquals($reflClass, $this->metadata->getReflectionClass());
    }

    public function testSerialize()
    {
        $this->metadata->addConstraint(new ConstraintA(['property1' => 'A']));
        $this->metadata->addConstraint(new ConstraintB(['groups' => 'TestGroup']));
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());
        $this->metadata->addGetterConstraint('lastName', new ConstraintB());

        $metadata = unserialize(serialize($this->metadata));

        self::assertEquals($this->metadata, $metadata);
    }

    public function testGroupSequencesWorkIfContainingDefaultGroup()
    {
        $this->metadata->setGroupSequence(['Foo', $this->metadata->getDefaultGroup()]);

        self::assertInstanceOf(GroupSequence::class, $this->metadata->getGroupSequence());
    }

    public function testGroupSequencesFailIfNotContainingDefaultGroup()
    {
        self::expectException(GroupDefinitionException::class);
        $this->metadata->setGroupSequence(['Foo', 'Bar']);
    }

    public function testGroupSequencesFailIfContainingDefault()
    {
        self::expectException(GroupDefinitionException::class);
        $this->metadata->setGroupSequence(['Foo', $this->metadata->getDefaultGroup(), Constraint::DEFAULT_GROUP]);
    }

    public function testGroupSequenceFailsIfGroupSequenceProviderIsSet()
    {
        self::expectException(GroupDefinitionException::class);
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequenceProvider(true);
        $metadata->setGroupSequence(['GroupSequenceProviderEntity', 'Foo']);
    }

    public function testGroupSequenceProviderFailsIfGroupSequenceIsSet()
    {
        self::expectException(GroupDefinitionException::class);
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequence(['GroupSequenceProviderEntity', 'Foo']);
        $metadata->setGroupSequenceProvider(true);
    }

    public function testGroupSequenceProviderFailsIfDomainClassIsInvalid()
    {
        self::expectException(GroupDefinitionException::class);
        $metadata = new ClassMetadata('stdClass');
        $metadata->setGroupSequenceProvider(true);
    }

    public function testGroupSequenceProvider()
    {
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequenceProvider(true);
        self::assertTrue($metadata->isGroupSequenceProvider());
    }

    public function testMergeConstraintsMergesGroupSequenceProvider()
    {
        $parent = new ClassMetadata(self::PROVIDERCLASS);
        $parent->setGroupSequenceProvider(true);

        $metadata = new ClassMetadata(self::PROVIDERCHILDCLASS);
        $metadata->mergeConstraints($parent);

        self::assertTrue($metadata->isGroupSequenceProvider());
    }

    /**
     * https://github.com/symfony/symfony/issues/11604.
     */
    public function testGetPropertyMetadataReturnsEmptyArrayWithoutConfiguredMetadata()
    {
        self::assertCount(0, $this->metadata->getPropertyMetadata('foo'), '->getPropertyMetadata() returns an empty collection if no metadata is configured for the given property');
    }

    /**
     * @requires PHP < 7.4
     */
    public function testCascadeConstraintIsNotAvailable()
    {
        $metadata = new ClassMetadata(CascadingEntity::class);

        self::expectException(ConstraintDefinitionException::class);
        self::expectExceptionMessage('The constraint "Symfony\Component\Validator\Constraints\Cascade" requires PHP 7.4.');

        $metadata->addConstraint(new Cascade());
    }

    /**
     * @requires PHP 7.4
     */
    public function testCascadeConstraint()
    {
        $metadata = new ClassMetadata(CascadingEntity::class);

        $metadata->addConstraint(new Cascade());

        self::assertSame(CascadingStrategy::CASCADE, $metadata->getCascadingStrategy());
        self::assertCount(4, $metadata->properties);
        self::assertSame([
            'requiredChild',
            'optionalChild',
            'staticChild',
            'children',
        ], $metadata->getConstrainedProperties());
    }
}

class ClassCompositeConstraint extends Composite
{
    public $nested;

    public function getDefaultOption(): ?string
    {
        return $this->getCompositeOption();
    }

    protected function getCompositeOption(): string
    {
        return 'nested';
    }

    public function getTargets()
    {
        return [self::CLASS_CONSTRAINT];
    }
}
