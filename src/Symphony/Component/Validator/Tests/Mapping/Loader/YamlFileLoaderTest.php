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
use Symphony\Component\Validator\Constraints\IsTrue;
use Symphony\Component\Validator\Mapping\ClassMetadata;
use Symphony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symphony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symphony\Component\Validator\Tests\Fixtures\ConstraintB;

class YamlFileLoaderTest extends TestCase
{
    public function testLoadClassMetadataReturnsFalseIfEmpty()
    {
        $loader = new YamlFileLoader(__DIR__.'/empty-mapping.yml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

        $this->assertFalse($loader->loadClassMetadata($metadata));

        $r = new \ReflectionProperty($loader, 'classes');
        $r->setAccessible(true);
        $this->assertSame(array(), $r->getValue($loader));
    }

    /**
     * @dataProvider provideInvalidYamlFiles
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidYamlFiles($path)
    {
        $loader = new YamlFileLoader(__DIR__.'/'.$path);
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

        $loader->loadClassMetadata($metadata);
    }

    public function provideInvalidYamlFiles()
    {
        return array(
            array('nonvalid-mapping.yml'),
            array('bad-format.yml'),
        );
    }

    /**
     * @see https://github.com/symphony/symphony/pull/12158
     */
    public function testDoNotModifyStateIfExceptionIsThrown()
    {
        $loader = new YamlFileLoader(__DIR__.'/nonvalid-mapping.yml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');
        try {
            $loader->loadClassMetadata($metadata);
        } catch (\InvalidArgumentException $e) {
            // Call again. Again an exception should be thrown
            $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('\InvalidArgumentException');
            $loader->loadClassMetadata($metadata);
        }
    }

    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

        $this->assertTrue($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadataReturnsFalseIfNotSuccessful()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata('\stdClass');

        $this->assertFalse($loader->loadClassMetadata($metadata));
    }

    public function testLoadClassMetadata()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');
        $expected->setGroupSequence(array('Foo', 'Entity'));
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new ConstraintB());
        $expected->addConstraint(new Callback('validateMe'));
        $expected->addConstraint(new Callback('validateMeStatic'));
        $expected->addConstraint(new Callback(array('Symphony\Component\Validator\Tests\Fixtures\CallbackClass', 'callback')));
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

    public function testLoadClassMetadataWithConstants()
    {
        $loader = new YamlFileLoader(__DIR__.'/mapping-with-constants.yml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\Entity');
        $expected->addPropertyConstraint('firstName', new Range(array('max' => PHP_INT_MAX)));

        $this->assertEquals($expected, $metadata);
    }

    public function testLoadGroupSequenceProvider()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symphony\Component\Validator\Tests\Fixtures\GroupSequenceProviderEntity');
        $expected->setGroupSequenceProvider(true);

        $this->assertEquals($expected, $metadata);
    }
}
