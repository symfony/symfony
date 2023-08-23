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
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Mapping\Loader\AttributeLoader;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;

class AttributeLoaderTest extends TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = $this->createAnnotationLoader();
        $metadata = new ClassMetadata($this->getFixtureNamespace().'\Entity');

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful()
    {
        $loader = $this->createAnnotationLoader();
        $metadata = new ClassMetadata('\stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata()
    {
        $loader = $this->createAnnotationLoader();
        $namespace = $this->getFixtureNamespace();

        $metadata = new ClassMetadata($namespace.'\Entity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata($namespace.'\Entity');
        $expected->setGroupSequence(['Foo', 'Entity']);
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new Callback(['Symfony\Component\Validator\Tests\Fixtures\CallbackClass', 'callback']));
        $expected->addConstraint(new Sequentially([
            new Expression('this.getFirstName() != null'),
        ]));
        $expected->addConstraint(new Callback(['callback' => 'validateMe', 'payload' => 'foo']));
        $expected->addConstraint(new Callback('validateMeStatic'));
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Range(['min' => 3]));
        $expected->addPropertyConstraint('firstName', new All([new NotNull(), new Range(['min' => 3])]));
        $expected->addPropertyConstraint('firstName', new All(['constraints' => [new NotNull(), new Range(['min' => 3])]]));
        $expected->addPropertyConstraint('firstName', new Collection([
            'foo' => [new NotNull(), new Range(['min' => 3])],
            'bar' => new Range(['min' => 5]),
            'baz' => new Required([new Email()]),
            'qux' => new Optional([new NotBlank()]),
        ], null, null, true));
        $expected->addPropertyConstraint('firstName', new Choice([
            'message' => 'Must be one of %choices%',
            'choices' => ['A', 'B'],
        ]));
        $expected->addPropertyConstraint('firstName', new AtLeastOneOf([
            new NotNull(),
            new Range(['min' => 3]),
        ], null, null, 'foo', null, false));
        $expected->addPropertyConstraint('firstName', new Sequentially([
            new NotBlank(),
            new Range(['min' => 5]),
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
     * Test MetaData merge with parent annotation.
     */
    public function testLoadParentClassMetadata()
    {
        $loader = $this->createAnnotationLoader();
        $namespace = $this->getFixtureNamespace();

        // Load Parent MetaData
        $parent_metadata = new ClassMetadata($namespace.'\EntityParent');
        $loader->loadClassMetadata($parent_metadata);

        $expected_parent = new ClassMetadata($namespace.'\EntityParent');
        $expected_parent->addPropertyConstraint('other', new NotNull());
        $expected_parent->getReflectionClass();

        $this->assertEquals($expected_parent, $parent_metadata);
    }

    /**
     * Test MetaData merge with parent annotation.
     */
    public function testLoadClassMetadataAndMerge()
    {
        $loader = $this->createAnnotationLoader();
        $namespace = $this->getFixtureNamespace();

        // Load Parent MetaData
        $parent_metadata = new ClassMetadata($namespace.'\EntityParent');
        $loader->loadClassMetadata($parent_metadata);

        $metadata = new ClassMetadata($namespace.'\Entity');
        $loader->loadClassMetadata($metadata);

        // Merge parent metaData.
        $metadata->mergeConstraints($parent_metadata);

        $expected_parent = new ClassMetadata($namespace.'\EntityParent');
        $expected_parent->addPropertyConstraint('other', new NotNull());
        $expected_parent->getReflectionClass();

        $expected = new ClassMetadata($namespace.'\Entity');

        $expected->setGroupSequence(['Foo', 'Entity']);
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new Callback(['Symfony\Component\Validator\Tests\Fixtures\CallbackClass', 'callback']));
        $expected->addConstraint(new Sequentially([
            new Expression('this.getFirstName() != null'),
        ]));
        $expected->addConstraint(new Callback(['callback' => 'validateMe', 'payload' => 'foo']));
        $expected->addConstraint(new Callback('validateMeStatic'));
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Range(['min' => 3]));
        $expected->addPropertyConstraint('firstName', new All([new NotNull(), new Range(['min' => 3])]));
        $expected->addPropertyConstraint('firstName', new All(['constraints' => [new NotNull(), new Range(['min' => 3])]]));
        $expected->addPropertyConstraint('firstName', new Collection([
            'foo' => [new NotNull(), new Range(['min' => 3])],
            'bar' => new Range(['min' => 5]),
            'baz' => new Required([new Email()]),
            'qux' => new Optional([new NotBlank()]),
        ], null, null, true));
        $expected->addPropertyConstraint('firstName', new Choice([
            'message' => 'Must be one of %choices%',
            'choices' => ['A', 'B'],
        ]));
        $expected->addPropertyConstraint('firstName', new AtLeastOneOf([
            new NotNull(),
            new Range(['min' => 3]),
        ], null, null, 'foo', null, false));
        $expected->addPropertyConstraint('firstName', new Sequentially([
            new NotBlank(),
            new Range(['min' => 5]),
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

    public function testLoadGroupSequenceProviderAnnotation()
    {
        $loader = $this->createAnnotationLoader();
        $namespace = $this->getFixtureNamespace();

        $metadata = new ClassMetadata($namespace.'\GroupSequenceProviderEntity');
        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata($namespace.'\GroupSequenceProviderEntity');
        $expected->setGroupSequenceProvider(true);
        $expected->getReflectionClass();

        $this->assertEquals($expected, $metadata);
    }

    protected function createAnnotationLoader(): AnnotationLoader
    {
        return new AttributeLoader();
    }

    protected function getFixtureNamespace(): string
    {
        return 'Symfony\Component\Validator\Tests\Fixtures\NestedAttribute';
    }
}
