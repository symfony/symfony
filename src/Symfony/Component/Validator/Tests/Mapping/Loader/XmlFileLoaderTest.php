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
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Traverse;
use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\Entity;
use Symfony\Component\Validator\Tests\Fixtures\Annotation\GroupSequenceProviderEntity;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintWithRequiredArgument;
use Symfony\Component\Validator\Tests\Fixtures\Entity_81;

class XmlFileLoaderTest extends TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata(Entity::class);

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata('\stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata(Entity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(Entity::class);
        $expected->setGroupSequence(['Foo', 'Entity']);
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new ConstraintB());
        $expected->addConstraint(new Callback('validateMe'));
        $expected->addConstraint(new Callback('validateMeStatic'));
        $expected->addConstraint(new Callback(['Symfony\Component\Validator\Tests\Fixtures\CallbackClass', 'callback']));
        $expected->addConstraint(new Traverse(false));
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Range(['min' => 3]));
        $expected->addPropertyConstraint('firstName', new Choice(['A', 'B']));
        $expected->addPropertyConstraint('firstName', new All([new NotNull(), new Range(['min' => 3])]));
        $expected->addPropertyConstraint('firstName', new All(['constraints' => [new NotNull(), new Range(['min' => 3])]]));
        $expected->addPropertyConstraint('firstName', new Collection(['fields' => [
            'foo' => [new NotNull(), new Range(['min' => 3])],
            'bar' => [new Range(['min' => 5])],
        ]]));
        $expected->addPropertyConstraint('firstName', new Choice([
            'message' => 'Must be one of %choices%',
            'choices' => ['A', 'B'],
        ]));
        $expected->addGetterConstraint('lastName', new NotNull());
        $expected->addGetterConstraint('valid', new IsTrue());
        $expected->addGetterConstraint('permissions', new IsTrue());

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadClassMetadataWithNonStrings()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping-non-strings.xml');
        $metadata = new ClassMetadata(Entity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(Entity::class);
        $expected->addPropertyConstraint('firstName', new Regex(['pattern' => '/^1/', 'match' => false]));

        $properties = $metadata->getPropertyMetadata('firstName');
        $constraints = $properties[0]->getConstraints();

        $this->assertFalse($constraints[0]->match);
    }

    public function testLoadClassMetadataWithRequiredArguments()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping-required-arg.xml');
        $metadata = new ClassMetadata(Entity_81::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(Entity_81::class);
        $expected->addPropertyConstraint('title', new ConstraintWithRequiredArgument('X'));

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadGroupSequenceProvider()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata(GroupSequenceProviderEntity::class);

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata(GroupSequenceProviderEntity::class);
        $expected->setGroupSequenceProvider(true);

        $this->assertEquals($expected, $metadata);
    }

    public function testThrowExceptionIfDocTypeIsSet()
    {
        $loader = new XmlFileLoader(__DIR__.'/withdoctype.xml');
        $metadata = new ClassMetadata(Entity::class);

        $this->expectException(MappingException::class);
        $loader->loadClassMetadata($metadata);
    }

    /**
     * @see https://github.com/symfony/symfony/pull/12158
     */
    public function testDoNotModifyStateIfExceptionIsThrown()
    {
        $loader = new XmlFileLoader(__DIR__.'/withdoctype.xml');
        $metadata = new ClassMetadata(Entity::class);

        try {
            $loader->loadClassMetadata($metadata);
        } catch (MappingException $e) {
            $this->expectException(MappingException::class);
            $loader->loadClassMetadata($metadata);
        }
    }
}
