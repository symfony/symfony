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

use Doctrine\Common\Annotations\AnnotationReader;
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
use Symfony\Component\Validator\Constraints\Valid;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;

class AnnotationLoaderTest extends TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $reader = new AnnotationReader();
        $loader = new AnnotationLoader($reader);
        $metadata = new ClassMetadata(Entity::class);

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful()
    {
        $loader = new AnnotationLoader(new AnnotationReader());
        $metadata = new ClassMetadata('\stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    /**
     * @dataProvider provideNamespaces
     */
    public function testLoadClassMetadata(string $namespace)
    {
        $loader = new AnnotationLoader(new AnnotationReader());
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

        // load reflection class so that the comparison passes
        $expected->getReflectionClass();

        $this->assertEquals($expected, $metadata);
    }

    /**
     * Test MetaData merge with parent annotation.
     *
     * @dataProvider provideNamespaces
     */
    public function testLoadParentClassMetadata(string $namespace)
    {
        $loader = new AnnotationLoader(new AnnotationReader());

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
     *
     * @dataProvider provideNamespaces
     */
    public function testLoadClassMetadataAndMerge(string $namespace)
    {
        $loader = new AnnotationLoader(new AnnotationReader());

        // Load Parent MetaData
        $parent_metadata = new ClassMetadata($namespace.'\EntityParent');
        $loader->loadClassMetadata($parent_metadata);

        $metadata = new ClassMetadata($namespace.'\Entity');

        // Merge parent metaData.
        $metadata->mergeConstraints($parent_metadata);

        $loader->loadClassMetadata($metadata);

        $expected_parent = new ClassMetadata($namespace.'\EntityParent');
        $expected_parent->addPropertyConstraint('other', new NotNull());
        $expected_parent->getReflectionClass();

        $expected = new ClassMetadata($namespace.'\Entity');
        $expected->mergeConstraints($expected_parent);

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

        // load reflection class so that the comparison passes
        $expected->getReflectionClass();

        $this->assertEquals($expected, $metadata);
    }

    /**
     * @dataProvider provideNamespaces
     */
    public function testLoadGroupSequenceProviderAnnotation(string $namespace)
    {
        $loader = new AnnotationLoader(new AnnotationReader());

        $metadata = new ClassMetadata($namespace.'\GroupSequenceProviderEntity');
        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata($namespace.'\GroupSequenceProviderEntity');
        $expected->setGroupSequenceProvider(true);
        $expected->getReflectionClass();

        $this->assertEquals($expected, $metadata);
    }

    public static function provideNamespaces(): iterable
    {
        yield 'annotations' => ['Symfony\Component\Validator\Tests\Fixtures\Annotation'];
        yield 'attributes' => ['Symfony\Component\Validator\Tests\Fixtures\Attribute'];
        yield 'nested_attributes' => ['Symfony\Component\Validator\Tests\Fixtures\NestedAttribute'];
    }
}
