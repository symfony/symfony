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

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Exception\MappingException;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintB;

class XmlFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\Entity');

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
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\Entity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\Entity');
        $expected->setGroupSequence(array('Foo', 'Entity'));
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new ConstraintB());
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

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadClassMetadataWithNonStrings()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping-non-strings.xml');
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\Entity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\Entity');
        $expected->addPropertyConstraint('firstName', new Regex(array('pattern' => '/^1/', 'match' => false)));

        $properties = $metadata->getPropertyMetadata('firstName');
        $constraints = $properties[0]->getConstraints();

        $this->assertFalse($constraints[0]->match);
    }

    public function testLoadGroupSequenceProvider()
    {
        $loader = new XmlFileLoader(__DIR__.'/constraint-mapping.xml');
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity');
        $expected->setGroupSequenceProvider(true);

        $this->assertEquals($expected, $metadata);
    }

    public function testThrowExceptionIfDocTypeIsSet()
    {
        $loader = new XmlFileLoader(__DIR__.'/withdoctype.xml');
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\Entity');

        $this->setExpectedException('\Symfony\Component\Validator\Exception\MappingException');
        $loader->loadClassMetadata($metadata);
    }

    /**
     * @see https://github.com/symfony/symfony/pull/12158
     */
    public function testDoNotModifyStateIfExceptionIsThrown()
    {
        $loader = new XmlFileLoader(__DIR__.'/withdoctype.xml');
        $metadata = new ClassMetadata('Symfony\Component\Validator\Tests\Fixtures\Entity');

        try {
            $loader->loadClassMetadata($metadata);
        } catch (MappingException $e) {
            $this->setExpectedException('\Symfony\Component\Validator\Exception\MappingException');
            $loader->loadClassMetadata($metadata);
        }
    }
}
