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
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Validator\Tests\Dummy\DummyGroupProvider;
use Symfony\Component\Validator\Tests\Fixtures\Attribute\GroupProviderDto;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithRequiredArgument;
use Symfony\Component\Validator\Tests\Fixtures\DummyEntityConstraintWithoutNamedArguments;
use Symfony\Component\Validator\Tests\Fixtures\Entity_81;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\Entity;
use Symfony\Component\Validator\Tests\Fixtures\NestedAttribute\GroupSequenceProviderEntity;
use Symfony\Component\Validator\Tests\Mapping\Loader\Fixtures\ConstraintWithNamedArguments;
use Symfony\Component\Validator\Tests\Mapping\Loader\Fixtures\ConstraintWithoutValueWithNamedArguments;

class YamlFileLoaderTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function testLoadClassMetadataReturnsFalseIfEmpty()
    {
        $loader = new YamlFileLoader(__DIR__.'/empty-mapping.yml');
        $metadata = new ClassMetadata(Entity::class);

        $this->assertFalse($loader->loadClassMetadata($metadata));

        $r = new \ReflectionProperty($loader, 'classes');
        $this->assertSame([], $r->getValue($loader));
    }

    /**
     * @dataProvider provideInvalidYamlFiles
     */
    public function testInvalidYamlFiles($path)
    {
        $loader = new YamlFileLoader(__DIR__.'/'.$path);
        $metadata = new ClassMetadata(Entity::class);

        $this->expectException(\InvalidArgumentException::class);

        $loader->loadClassMetadata($metadata);
    }

    public static function provideInvalidYamlFiles()
    {
        return [
            ['nonvalid-mapping.yml'],
            ['bad-format.yml'],
        ];
    }

    /**
     * @see https://github.com/symfony/symfony/pull/12158
     */
    public function testDoNotModifyStateIfExceptionIsThrown()
    {
        $loader = new YamlFileLoader(__DIR__.'/nonvalid-mapping.yml');
        $metadata = new ClassMetadata(Entity::class);
        try {
            $loader->loadClassMetadata($metadata);
        } catch (\InvalidArgumentException $e) {
            // Call again. Again an exception should be thrown
            $this->expectException(\InvalidArgumentException::class);
            $loader->loadClassMetadata($metadata);
        }
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata(Entity::class);

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata(\stdClass::class);

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata(Entity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(Entity::class);
        $expected->setGroupSequence(['Foo', 'Entity']);
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new ConstraintB());
        $expected->addConstraint(new Callback('validateMe'));
        $expected->addConstraint(new Callback('validateMeStatic'));
        $expected->addConstraint(new Callback(['Symfony\Component\Validator\Tests\Fixtures\CallbackClass', 'callback']));
        $expected->addConstraint(new ConstraintWithoutValueWithNamedArguments());
        $expected->addConstraint(new ConstraintWithNamedArguments('foo'));
        $expected->addConstraint(new ConstraintWithNamedArguments(['foo', 'bar']));
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Range(min: 3));
        $expected->addPropertyConstraint('firstName', new Choice(['A', 'B']));
        $expected->addPropertyConstraint('firstName', new All(constraints: [new NotNull(), new Range(min: 3)]));
        $expected->addPropertyConstraint('firstName', new All(constraints: [new NotNull(), new Range(min: 3)]));
        $expected->addPropertyConstraint('firstName', new Collection(fields: [
            'foo' => [new NotNull(), new Range(min: 3)],
            'bar' => [new Range(min: 5)],
        ]));
        $expected->addPropertyConstraint('firstName', new Choice(
            message: 'Must be one of %choices%',
            choices: ['A', 'B'],
        ));
        $expected->addGetterConstraint('lastName', new NotNull());
        $expected->addGetterConstraint('valid', new IsTrue());
        $expected->addGetterConstraint('permissions', new IsTrue());

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadClassMetadataWithConstants()
    {
        $loader = new YamlFileLoader(__DIR__.'/mapping-with-constants.yml');
        $metadata = new ClassMetadata(Entity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(Entity::class);
        $expected->addPropertyConstraint('firstName', new Range(max: \PHP_INT_MAX));

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadClassMetadataWithRequiredArguments()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping-required-arg.yml');
        $metadata = new ClassMetadata(Entity_81::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(Entity_81::class);
        $expected->addPropertyConstraint('title', new ConstraintWithRequiredArgument('X'));

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadGroupSequenceProvider()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata(GroupSequenceProviderEntity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(GroupSequenceProviderEntity::class);
        $expected->setGroupSequenceProvider(true);

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadGroupProvider()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata(GroupProviderDto::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(GroupProviderDto::class);
        $expected->setGroupProvider(DummyGroupProvider::class);
        $expected->setGroupSequenceProvider(true);

        $this->assertEquals($expected, $metadata);
    }

    /**
     * @group legacy
     */
    public function testLoadConstraintWithoutNamedArgumentsSupport()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-without-named-arguments-support.yml');
        $metadata = new ClassMetadata(DummyEntityConstraintWithoutNamedArguments::class);

        $this->expectUserDeprecationMessage('Since symfony/validator 7.2: Using constraints not supporting named arguments is deprecated. Try adding the HasNamedArguments attribute to Symfony\Component\Validator\Tests\Mapping\Loader\Fixtures\ConstraintWithoutNamedArguments.');

        $loader->loadClassMetadata($metadata);
    }
}
