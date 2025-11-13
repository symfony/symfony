<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Attribute\ExtendsValidationFor;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Required;
use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Tests\Dummy\DummyGroupProvider;
use Symfony\Component\Validator\Tests\Fixtures\Attribute\GroupProviderDto;
use Symfony\Component\Validator\Tests\Fixtures\CallbackClass;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\EntityParent;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\GroupSequenceProviderEntity;

class AttributeLoaderTest extends TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new AttributeLoader();
        $metadata = new ClassMetadata(Entity::class);

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful()
    {
        $loader = new AttributeLoader();
        $metadata = new ClassMetadata('\stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata()
    {
        $loader = new AttributeLoader();

        $metadata = new ClassMetadata(Entity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(Entity::class);
        $expected->setGroupSequence(['Foo', 'Entity']);
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new Callback([CallbackClass::class, 'callback']));
        $expected->addConstraint(new Sequentially([
            new Expression('this.getFirstName() != null'),
        ]));
        $expected->addConstraint(new Callback(callback: 'validateMe', payload: 'foo'));
        $expected->addConstraint(new Callback('validateMeStatic'));
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Range(min: 3));
        $expected->addPropertyConstraint('firstName', new All(constraints: [new NotNull(), new Range(min: 3)]));
        $expected->addPropertyConstraint('firstName', new All(constraints: [new NotNull(), new Range(min: 3)]));
        $expected->addPropertyConstraint('firstName', new Collection(fields: [
            'foo' => [new NotNull(), new Range(min: 3)],
            'bar' => new Range(min: 5),
            'baz' => new Required([new Email()]),
            'qux' => new Optional([new NotBlank()]),
        ], allowExtraFields: true));
        $expected->addPropertyConstraint('firstName', new Choice(
            message: 'Must be one of %choices%',
            choices: ['A', 'B'],
        ));
        $expected->addPropertyConstraint('firstName', new AtLeastOneOf([
            new NotNull(),
            new Range(min: 3),
        ], null, null, 'foo', null, false));
        $expected->addPropertyConstraint('firstName', new Sequentially([
            new NotBlank(),
            new Range(min: 5),
        ]));
        $expected->addPropertyConstraint('childA', new Valid());
        $expected->addPropertyConstraint('childB', new Valid());
        $expected->addGetterConstraint('lastName', new NotNull());
        $expected->addGetterMethodConstraint('valid', 'isValid', new IsTrue());
        $expected->addGetterConstraint('permissions', new IsTrue());
        $expected->addPropertyConstraint('other', new Type('integer'));

        // load reflection class so that the comparison passes
        $expected->getReflectionClass();

        $this->assertEquals($expected, $metadata);
    }

    /**
     * Test MetaData merge with parent attribute.
     */
    public function testLoadParentClassMetadata()
    {
        $loader = new AttributeLoader();

        // Load Parent MetaData
        $parent_metadata = new ClassMetadata(EntityParent::class);
        $loader->loadClassMetadata($parent_metadata);

        $expected_parent = new ClassMetadata(EntityParent::class);
        $expected_parent->addPropertyConstraint('other', new NotNull());
        $expected_parent->getReflectionClass();

        $this->assertEquals($expected_parent, $parent_metadata);
    }

    /**
     * Test MetaData merge with parent attribute.
     */
    public function testLoadClassMetadataAndMerge()
    {
        $loader = new AttributeLoader();

        // Load Parent MetaData
        $parent_metadata = new ClassMetadata(EntityParent::class);
        $loader->loadClassMetadata($parent_metadata);

        $metadata = new ClassMetadata(Entity::class);
        $loader->loadClassMetadata($metadata);

        // Merge parent metaData.
        $metadata->mergeConstraints($parent_metadata);

        $expected_parent = new ClassMetadata(EntityParent::class);
        $expected_parent->addPropertyConstraint('other', new NotNull());
        $expected_parent->getReflectionClass();

        $expected = new ClassMetadata(Entity::class);

        $expected->setGroupSequence(['Foo', 'Entity']);
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new Callback([CallbackClass::class, 'callback']));
        $expected->addConstraint(new Sequentially([
            new Expression('this.getFirstName() != null'),
        ]));
        $expected->addConstraint(new Callback(callback: 'validateMe', payload: 'foo'));
        $expected->addConstraint(new Callback('validateMeStatic'));
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Range(min: 3));
        $expected->addPropertyConstraint('firstName', new All(constraints: [new NotNull(), new Range(min: 3)]));
        $expected->addPropertyConstraint('firstName', new All(constraints: [new NotNull(), new Range(min: 3)]));
        $expected->addPropertyConstraint('firstName', new Collection(fields: [
            'foo' => [new NotNull(), new Range(min: 3)],
            'bar' => new Range(min: 5),
            'baz' => new Required([new Email()]),
            'qux' => new Optional([new NotBlank()]),
        ], allowExtraFields: true));
        $expected->addPropertyConstraint('firstName', new Choice(
            message: 'Must be one of %choices%',
            choices: ['A', 'B'],
        ));
        $expected->addPropertyConstraint('firstName', new AtLeastOneOf([
            new NotNull(),
            new Range(min: 3),
        ], null, null, 'foo', null, false));
        $expected->addPropertyConstraint('firstName', new Sequentially([
            new NotBlank(),
            new Range(min: 5),
        ]));
        $expected->addPropertyConstraint('childA', new Valid());
        $expected->addPropertyConstraint('childB', new Valid());
        $expected->addGetterConstraint('lastName', new NotNull());
        $expected->addGetterMethodConstraint('valid', 'isValid', new IsTrue());
        $expected->addGetterConstraint('permissions', new IsTrue());
        $expected->addPropertyConstraint('other', new Type('integer'));

        // load reflection class so that the comparison passes
        $expected->getReflectionClass();
        $expected->mergeConstraints($expected_parent);

        $this->assertEquals($expected, $metadata);

        $otherMetadata = $metadata->getPropertyMetadata('other');
        $this->assertCount(2, $otherMetadata);
        $this->assertInstanceOf(Type::class, $otherMetadata[0]->getConstraints()[0]);
        $this->assertInstanceOf(NotNull::class, $otherMetadata[1]->getConstraints()[0]);
    }

    public function testLoadGroupSequenceProviderAttribute()
    {
        $loader = new AttributeLoader();

        $metadata = new ClassMetadata(GroupSequenceProviderEntity::class);
        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(GroupSequenceProviderEntity::class);
        $expected->setGroupSequenceProvider(true);
        $expected->getReflectionClass();

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadExternalGroupSequenceProvider()
    {
        $loader = new AttributeLoader();

        $metadata = new ClassMetadata(GroupProviderDto::class);
        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(GroupProviderDto::class);
        $expected->setGroupProvider(DummyGroupProvider::class);
        $expected->setGroupSequenceProvider(true);
        $expected->getReflectionClass();

        $this->assertEquals($expected, $metadata);
    }

    public function testGetMappedClasses()
    {
        $classes = [
            'App\Entity\User' => ['App\Entity\User'],
            'App\Entity\Product' => ['App\Entity\Product'],
            'App\Entity\Order' => ['App\Entity\Order'],
        ];
        $loader = new AttributeLoader(false, $classes);

        $this->assertSame(array_keys($classes), $loader->getMappedClasses());
    }

    public function testLoadClassMetadataReturnsFalseForUnmappedClass()
    {
        $loader = new AttributeLoader(false, ['App\Entity\User' => ['App\Entity\User']]);
        $metadata = new ClassMetadata('App\Entity\Product');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseForClassWithoutAttributes()
    {
        $loader = new AttributeLoader(false, ['stdClass' => ['stdClass']]);
        $metadata = new ClassMetadata('stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataForMappedClassWithAttributes()
    {
        $loader = new AttributeLoader(false, [Entity::class => [Entity::class]]);
        $metadata = new ClassMetadata(Entity::class);

        $this->assertTrue($loader->loadClassMetadata($metadata));

        $this->assertNotEmpty($metadata->getConstraints());
    }

    public function testLoadClassMetadataFromExplicitAttributeMappings()
    {
        $targetClass = _AttrMap_Target::class;
        $sourceClass = _AttrMap_Source::class;

        $loader = new AttributeLoader(false, [$targetClass => [$sourceClass]]);
        $metadata = new ClassMetadata($targetClass);

        $this->assertTrue($loader->loadClassMetadata($metadata));
        $this->assertInstanceOf(NotBlank::class, $metadata->getPropertyMetadata('name', $sourceClass)[0]->getConstraints()[0]);
    }

    public function testLoadClassMetadataWithClassLevelConstraints()
    {
        $targetClass = _AttrMap_Target::class;
        $sourceClass = _AttrMap_ClassLevelSource::class;

        $loader = new AttributeLoader(false, [$targetClass => [$sourceClass]]);
        $metadata = new ClassMetadata($targetClass);

        $this->assertTrue($loader->loadClassMetadata($metadata));

        // Check that class-level constraints are added to the target
        $constraints = $metadata->getConstraints();
        $this->assertCount(2, $constraints);

        // Check for Callback constraint
        $callbackConstraint = null;
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Callback) {
                $callbackConstraint = $constraint;
                break;
            }
        }
        $this->assertInstanceOf(Callback::class, $callbackConstraint);
        $this->assertEquals('validateClass', $callbackConstraint->callback);

        // Check for Expression constraint
        $expressionConstraint = null;
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Expression) {
                $expressionConstraint = $constraint;
                break;
            }
        }
        $this->assertInstanceOf(Expression::class, $expressionConstraint);
        $this->assertEquals('this.name != null', $expressionConstraint->expression);

        // Check that property constraints are also added
        $this->assertInstanceOf(NotBlank::class, $metadata->getPropertyMetadata('name', $sourceClass)[0]->getConstraints()[0]);
    }
}

class _AttrMap_Target
{
    public string $name;

    public function getName()
    {
        return $this->name;
    }

    public function validateClass()
    {
        // This method will be called by the Callback constraint
        return true;
    }
}

#[ExtendsValidationFor(_AttrMap_Target::class)]
class _AttrMap_Source
{
    #[NotBlank] public string $name;
}

#[ExtendsValidationFor(_AttrMap_Target::class)]
#[Callback('validateClass')]
#[Expression('this.name != null')]
class _AttrMap_ClassLevelSource
{
    #[NotBlank]
    public string $name = '';
}
