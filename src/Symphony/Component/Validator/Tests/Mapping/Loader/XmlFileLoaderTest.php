<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Validator\Tests\Mapping\Loader;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Validator\Constraints\All;
use Symphony\Component\Validator\Constraints\Callback;
use Symphony\Component\Validator\Constraints\Choice;
use Symphony\Component\Validator\Constraints\Collection;
use Symphony\Component\Validator\Constraints\NotNull;
use Symphony\Component\Validator\Constraints\Range;
use Symphony\Component\Validator\Constraints\Regex;
use Symphony\Component\Validator\Constraints\IsTrue;
use Symphony\Component\Validator\Constraints\Traverse;
use Symphony\Component\Validator\Exception\MappingException;
use Symphony\Component\Validator\Mapping\ClassMetadata;
use Symphony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symphony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symphony\Component\Validator\Tests\Fixtures\ConstraintB;

class XmlFileLoaderTest extends TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

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
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');
        $expected->setGroupSequence(array('Foo', 'Entity'));
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new ConstraintB());
        $expected->addConstraint(new Callback('validateMe'));
        $expected->addConstraint(new Callback('validateMeStatic'));
        $expected->addConstraint(new Callback(array('Symphony\Component\Validator\Tests\Fixtures\CallbackClass', 'callback')));
        $expected->addConstraint(new Traverse(false));
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Range(array('min' => 3)));
        $expected->addPropertyConstraint('firstName', new Choice(array('A', 'B')));
        $expected->addPropertyConstraint('firstName', new All(array(new NotNull(), new Range(array('min' => 3)))));
        $expected->addPropertyConstraint('firstName', new All(array('constraints' => array(new NotNull(), new Range(array('min' => 3))))));
        $expected->addPropertyConstraint('firstName', new Collection(array('fields' => array(
            'foo' => array(new NotNull(), new Range(array('min' => 3))),
            'bar' => array(new Range(array('min' => 5))),
        ))));
        $expected->addPropertyConstraint('firstName', new Choice(array(
            'message' => 'Must be one of %choices%',
            'choices' => array('A', 'B'),
        )));
        $expected->addGetterConstraint('lastName', new NotNull());
        $expected->addGetterConstraint('valid', new IsTrue());
        $expected->addGetterConstraint('permissions', new IsTrue());

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadClassMetadataWithNonStrings()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping-non-strings.xml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');
        $expected->addPropertyConstraint('firstName', new Regex(array('pattern' => '/^1/', 'match' => false)));

        $properties = $metadata->getPropertyMetadata('firstName');
        $constraints = $properties[0]->getConstraints();

        $this->assertFalse($constraints[0]->match);
    }

    public function testLoadGroupSequenceProvider()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity');
        $expected->setGroupSequenceProvider(true);

        $this->assertEquals($expected, $metadata);
    }

    public function testThrowExceptionIfDocTypeIsSet()
    {
        $loader = new XmlFileLoader(__DIR__.'/withdoctype.xml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('\Symphony\Component\Validator\Exception\MappingException');
        $loader->loadClassMetadata($metadata);
    }

    /**
     * @see https://github.com/symphony/symphony/pull/12158
     */
    public function testDoNotModifyStateIfExceptionIsThrown()
    {
        $loader = new XmlFileLoader(__DIR__.'/withdoctype.xml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

        try {
            $loader->loadClassMetadata($metadata);
        } catch (MappingException $e) {
            $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('\Symphony\Component\Validator\Exception\MappingException');
            $loader->loadClassMetadata($metadata);
        }
    }
}
