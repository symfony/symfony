<?php

namespace Symfony\Tests\Component\Validator\Mapping\Loader;

require_once __DIR__.'/../../Fixtures/Entity.php';
require_once __DIR__.'/../../Fixtures/ConstraintA.php';

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Min;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\YamlFileLoader;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintA;

class YamlFileLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadClassMetadataReturnsTrueIfSuccessful()
    {
        $loader = new YamlFileLoader(__DIR__.'/constraint-mapping.yml');
        $metadata = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\Entity');

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
        $metadata = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\Entity');

        $loader->loadClassMetadata($metadata);

        $expected = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\Entity');
        $expected->addConstraint(new NotNull());
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new Min(3));
        $expected->addConstraint(new Choice(array('A', 'B')));
        $expected->addConstraint(new All(array(new NotNull(), new Min(3))));
        $expected->addConstraint(new All(array('constraints' => array(new NotNull(), new Min(3)))));
        $expected->addConstraint(new Collection(array('fields' => array(
            'foo' => array(new NotNull(), new Min(3)),
            'bar' => array(new Min(5)),
        ))));
        $expected->addPropertyConstraint('firstName', new Choice(array(
            'message' => 'Must be one of %choices%',
            'choices' => array('A', 'B'),
        )));
        $expected->addGetterConstraint('lastName', new NotNull());

        $this->assertEquals($expected, $metadata);
    }
}
