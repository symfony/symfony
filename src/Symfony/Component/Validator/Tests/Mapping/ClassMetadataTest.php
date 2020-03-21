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
use Symfony\Component\Validator\Constraints\DisableOverridingPropertyConstraints;
use Symfony\Component\Validator\Constraints\EnableOverridingPropertyConstraints;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\OverridingPropertyConstraintsStrategy;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\PropertyConstraint;

class ClassMetadataTest extends TestCase
{
    const CLASSNAME = 'Symfony\Component\Validator\Tests\Fixtures\Entity';
    const PARENTCLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityParent';
    const PROVIDERCLASS = 'Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity';
    const PROVIDERCHILDCLASS = 'Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderChildEntity';

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
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->metadata->addConstraint(new Valid());
    }

    public function testAddConstraintRequiresClassConstraints()
    {
        $this->expectException('Symfony\Component\Validator\Exception\ConstraintDefinitionException');

        $this->metadata->addConstraint(new PropertyConstraint());
    }

    public function testAddPropertyConstraints()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());
        $this->metadata->addPropertyConstraint('lastName', new ConstraintB());

        $this->assertEquals(['firstName', 'lastName'], $this->metadata->getConstrainedProperties());
    }

    public function testAddMultiplePropertyConstraints()
    {
        $this->metadata->addPropertyConstraints('lastName', [new ConstraintA(), new ConstraintB()]);

        $constraints = [
            new ConstraintA(['groups' => ['Default', 'Entity']]),
            new ConstraintB(['groups' => ['Default', 'Entity']]),
        ];

        $properties = $this->metadata->getPropertyMetadata('lastName');

        $this->assertCount(1, $properties);
        $this->assertEquals('lastName', $properties[0]->getName());
        $this->assertEquals($constraints, $properties[0]->getConstraints());
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

        $this->assertCount(1, $properties);
        $this->assertEquals('getLastName', $properties[0]->getName());
        $this->assertEquals($constraints, $properties[0]->getConstraints());
    }

    public function testAddMultipleGetterConstraints()
    {
        $this->metadata->addGetterConstraints('lastName', [new ConstraintA(), new ConstraintB()]);

        $constraints = [
            new ConstraintA(['groups' => ['Default', 'Entity']]),
            new ConstraintB(['groups' => ['Default', 'Entity']]),
        ];

        $properties = $this->metadata->getPropertyMetadata('lastName');

        $this->assertCount(1, $properties);
        $this->assertEquals('getLastName', $properties[0]->getName());
        $this->assertEquals($constraints, $properties[0]->getConstraints());
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

        $this->assertEquals($constraints, $this->metadata->getConstraints());
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

        $this->assertCount(1, $members);
        $this->assertEquals(self::PARENTCLASS, $members[0]->getClassName());
        $this->assertEquals($constraints, $members[0]->getConstraints());
        $this->assertEquals($constraintsByGroup, $members[0]->constraintsByGroup);
    }

    public function testMemberMetadatas()
    {
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());

        $this->assertTrue($this->metadata->hasPropertyMetadata('firstName'));
        $this->assertFalse($this->metadata->hasPropertyMetadata('non_existent_field'));
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

        $this->assertCount(2, $members);
        $this->assertEquals(self::PARENTCLASS, $members[0]->getClassName());
        $this->assertEquals($parentConstraints, $members[0]->getConstraints());
        $this->assertEquals(self::CLASSNAME, $members[1]->getClassName());
        $this->assertEquals($constraints, $members[1]->getConstraints());
    }

    public function testGetReflectionClass()
    {
        $reflClass = new \ReflectionClass(self::CLASSNAME);

        $this->assertEquals($reflClass, $this->metadata->getReflectionClass());
    }

    public function testSerialize()
    {
        $this->metadata->addConstraint(new ConstraintA(['property1' => 'A']));
        $this->metadata->addConstraint(new ConstraintB(['groups' => 'TestGroup']));
        $this->metadata->addPropertyConstraint('firstName', new ConstraintA());
        $this->metadata->addGetterConstraint('lastName', new ConstraintB());

        $metadata = unserialize(serialize($this->metadata));

        $this->assertEquals($this->metadata, $metadata);
    }

    public function testGroupSequencesWorkIfContainingDefaultGroup()
    {
        $this->metadata->setGroupSequence(['Foo', $this->metadata->getDefaultGroup()]);

        $this->assertInstanceOf('Symfony\Component\Validator\Constraints\GroupSequence', $this->metadata->getGroupSequence());
    }

    public function testGroupSequencesFailIfNotContainingDefaultGroup()
    {
        $this->expectException('Symfony\Component\Validator\Exception\GroupDefinitionException');
        $this->metadata->setGroupSequence(['Foo', 'Bar']);
    }

    public function testGroupSequencesFailIfContainingDefault()
    {
        $this->expectException('Symfony\Component\Validator\Exception\GroupDefinitionException');
        $this->metadata->setGroupSequence(['Foo', $this->metadata->getDefaultGroup(), Constraint::DEFAULT_GROUP]);
    }

    public function testGroupSequenceFailsIfGroupSequenceProviderIsSet()
    {
        $this->expectException('Symfony\Component\Validator\Exception\GroupDefinitionException');
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequenceProvider(true);
        $metadata->setGroupSequence(['GroupSequenceProviderEntity', 'Foo']);
    }

    public function testGroupSequenceProviderFailsIfGroupSequenceIsSet()
    {
        $this->expectException('Symfony\Component\Validator\Exception\GroupDefinitionException');
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequence(['GroupSequenceProviderEntity', 'Foo']);
        $metadata->setGroupSequenceProvider(true);
    }

    public function testGroupSequenceProviderFailsIfDomainClassIsInvalid()
    {
        $this->expectException('Symfony\Component\Validator\Exception\GroupDefinitionException');
        $metadata = new ClassMetadata('stdClass');
        $metadata->setGroupSequenceProvider(true);
    }

    public function testGroupSequenceProvider()
    {
        $metadata = new ClassMetadata(self::PROVIDERCLASS);
        $metadata->setGroupSequenceProvider(true);
        $this->assertTrue($metadata->isGroupSequenceProvider());
    }

    public function testMergeConstraintsMergesGroupSequenceProvider()
    {
        $parent = new ClassMetadata(self::PROVIDERCLASS);
        $parent->setGroupSequenceProvider(true);

        $metadata = new ClassMetadata(self::PROVIDERCHILDCLASS);
        $metadata->mergeConstraints($parent);

        $this->assertTrue($metadata->isGroupSequenceProvider());
    }

    /**
     * https://github.com/symfony/symfony/issues/11604.
     */
    public function testGetPropertyMetadataReturnsEmptyArrayWithoutConfiguredMetadata()
    {
        $this->assertCount(0, $this->metadata->getPropertyMetadata('foo'), '->getPropertyMetadata() returns an empty collection if no metadata is configured for the given property');
    }

    public function testOverrideConstraintsIfOverridingEnabledInProperty()
    {
        $parentMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ParentClass');
        $parentMetadata->addPropertyConstraint('example', new GreaterThan(0));

        $childMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $childMetadata->addPropertyConstraint('example', new EnableOverridingPropertyConstraints());
        $childMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $childMetadata->mergeConstraints($parentMetadata);

        $expectedMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $expectedMetadata->addPropertyConstraint('example', new EnableOverridingPropertyConstraints());
        $expectedMetadata->addPropertyConstraint('example', new GreaterThan(1));

        $this->assertEquals($expectedMetadata, $childMetadata);
    }

    public function testOverrideConstraintsIfOverridingEnabledInPropertyAndDisabledInClass()
    {
        $parentMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ParentClass');
        $parentMetadata->addPropertyConstraint('example', new GreaterThan(0));

        $childMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $childMetadata->addConstraint(new DisableOverridingPropertyConstraints());
        $childMetadata->addPropertyConstraint('example', new EnableOverridingPropertyConstraints());
        $childMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $childMetadata->mergeConstraints($parentMetadata);

        $expectedMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $expectedMetadata->addConstraint(new DisableOverridingPropertyConstraints());
        $expectedMetadata->addPropertyConstraint('example', new EnableOverridingPropertyConstraints());
        $expectedMetadata->addPropertyConstraint('example', new GreaterThan(1));

        $this->assertEquals($expectedMetadata, $childMetadata);
    }

    public function testOverrideConstraintsIfOverridingEnabledInClass()
    {
        $parentMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ParentClass');
        $parentMetadata->addPropertyConstraint('example', new GreaterThan(0));

        $childMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $childMetadata->addConstraint(new EnableOverridingPropertyConstraints());
        $childMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $childMetadata->mergeConstraints($parentMetadata);

        $expectedMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $expectedMetadata->addConstraint(new EnableOverridingPropertyConstraints());
        $expectedMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $expectedMetadata->getPropertyMetadata('example')[0]->overridingPropertyConstraintsStrategy = OverridingPropertyConstraintsStrategy::ENABLED;

        $this->assertEquals($expectedMetadata, $childMetadata);
    }

    public function testOverrideConstraintsIfOverridingEnabledInClassAndDisabledInParentClass()
    {
        $parentMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ParentClass');
        $parentMetadata->addConstraint(new DisableOverridingPropertyConstraints());
        $parentMetadata->addPropertyConstraint('example', new GreaterThan(0));

        $childMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $childMetadata->addConstraint(new EnableOverridingPropertyConstraints());
        $childMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $childMetadata->mergeConstraints($parentMetadata);

        $expectedMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $expectedMetadata->addConstraint(new EnableOverridingPropertyConstraints());
        $expectedMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $expectedMetadata->getPropertyMetadata('example')[0]->overridingPropertyConstraintsStrategy = OverridingPropertyConstraintsStrategy::ENABLED;

        $this->assertEquals($expectedMetadata, $childMetadata);
    }

    public function testOverrideConstraintsIfOverridingEnabledInParentClass()
    {
        $parentMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ParentClass');
        $parentMetadata->addConstraint(new EnableOverridingPropertyConstraints());
        $parentMetadata->addPropertyConstraint('example', new GreaterThan(0));

        $childMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $childMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $childMetadata->mergeConstraints($parentMetadata);

        $expectedMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $expectedMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $expectedMetadata->overridingPropertyConstraintsStrategy = OverridingPropertyConstraintsStrategy::ENABLED;
        $expectedMetadata->getPropertyMetadata('example')[0]->overridingPropertyConstraintsStrategy = OverridingPropertyConstraintsStrategy::ENABLED;

        $this->assertEquals($expectedMetadata, $childMetadata);
    }

    public function testOverrideConstraintsIfOverridingEnabledInParentClassProperty()
    {
        $parentMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ParentClass');
        $parentMetadata->addPropertyConstraint('example', new EnableOverridingPropertyConstraints());
        $parentMetadata->addPropertyConstraint('example', new GreaterThan(0));

        $childMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $childMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $childMetadata->mergeConstraints($parentMetadata);

        $expectedMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $expectedMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $expectedMetadata->getPropertyMetadata('example')[0]->overridingPropertyConstraintsStrategy = OverridingPropertyConstraintsStrategy::ENABLED;

        $this->assertEquals($expectedMetadata, $childMetadata);
    }

    public function testMergeConstraintsIfOverridingEnabledInClassAndDisabledInProperty()
    {
        $parentMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ParentClass');
        $parentMetadata->addPropertyConstraint('example', new GreaterThan(0));

        $childMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $childMetadata->addConstraint(new EnableOverridingPropertyConstraints());
        $childMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $childMetadata->addPropertyConstraint('example', new DisableOverridingPropertyConstraints());
        $childMetadata->mergeConstraints($parentMetadata);

        $childConstraints = [];
        foreach ($childMetadata->getPropertyMetadata('example') as $member) {
            $childConstraints[] = $member->getConstraints()[0];
        }

        $expectedConstraints = [
            new GreaterThan(['value' => 1, 'groups' => ['Default', 'ChildClass']]),
            new GreaterThan(['value' => 0, 'groups' => ['Default', 'ParentClass', 'ChildClass']]),
        ];

        $this->assertEquals($expectedConstraints, $childConstraints);
    }

    public function testMergeConstraintsIfOverridingEnabledInParentClassAndDisabledInChildClass()
    {
        $parentMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ParentClass');
        $parentMetadata->addConstraint(new EnableOverridingPropertyConstraints());
        $parentMetadata->addPropertyConstraint('example', new GreaterThan(0));

        $childMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $childMetadata->addConstraint(new DisableOverridingPropertyConstraints());
        $childMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $childMetadata->mergeConstraints($parentMetadata);

        $childConstraints = [];
        foreach ($childMetadata->getPropertyMetadata('example') as $member) {
            $childConstraints[] = $member->getConstraints()[0];
        }

        $expectedConstraints = [
            new GreaterThan(['value' => 1, 'groups' => ['Default', 'ChildClass']]),
            new GreaterThan(['value' => 0, 'groups' => ['Default', 'ParentClass', 'ChildClass']]),
        ];

        $this->assertEquals($expectedConstraints, $childConstraints);
    }

    public function testMergeConstraintsIfOverridingEnabledInParentClassPropertyAndDisabledInChildClassProperty()
    {
        $parentMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ParentClass');
        $parentMetadata->addPropertyConstraint('example', new EnableOverridingPropertyConstraints());
        $parentMetadata->addPropertyConstraint('example', new GreaterThan(0));

        $childMetadata = new ClassMetadata('\Symfony\Component\Validator\Tests\Mapping\ChildClass');
        $childMetadata->addPropertyConstraint('example', new DisableOverridingPropertyConstraints());
        $childMetadata->addPropertyConstraint('example', new GreaterThan(1));
        $childMetadata->mergeConstraints($parentMetadata);

        $childConstraints = [];
        foreach ($childMetadata->getPropertyMetadata('example') as $member) {
            $childConstraints[] = $member->getConstraints()[0];
        }

        $expectedConstraints = [
            new GreaterThan(['value' => 1, 'groups' => ['Default', 'ChildClass']]),
            new GreaterThan(['value' => 0, 'groups' => ['Default', 'ParentClass', 'ChildClass']]),
        ];

        $this->assertEquals($expectedConstraints, $childConstraints);
    }
}

class ParentClass
{
    public $example = 0;
}

class ChildClass extends ParentClass
{
    public $example = 1;    // overrides parent property of same name
}
