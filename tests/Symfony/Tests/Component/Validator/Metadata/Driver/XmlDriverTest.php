<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Validator\Mapping\Loader;

require_once __DIR__.'/../../Fixtures/Entity.php';
require_once __DIR__.'/../../Fixtures/ConstraintA.php';
require_once __DIR__.'/../../Fixtures/ConstraintB.php';

use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Min;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintA;
use Symfony\Tests\Component\Validator\Fixtures\ConstraintB;

use Metadata\Driver\FileLocator;
use Symfony\Component\Validator\Metadata\ClassMetadata;
use Symfony\Component\Validator\Metadata\Driver\XmlDriver;

class XmlDriverTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->locator = new FileLocator(array(
           'Symfony\Tests\Component\Validator\Fixtures' => __DIR__,
        ));

        $this->driver = new XmlDriver($this->locator);
    }

    public function testLoadClassMetadataReturnsMetadataInstanceIfSuccessful()
    {
        $class = new \ReflectionClass('Symfony\Tests\Component\Validator\Fixtures\Entity');
        $this->assertInstanceOf('Symfony\Component\Validator\Metadata\ClassMetadata', $this->driver->loadMetadataForClass($class));
    }

    public function testLoadClassMetadataReturnsNullIfNotSuccessful()
    {
        $class = new \ReflectionClass('stdClass');
        $this->assertInternalType('null', $this->driver->loadMetadataForClass($class));
    }

    public function testLoadClassMetadata()
    {
        $class = new \ReflectionClass('Symfony\Tests\Component\Validator\Fixtures\Entity');
        $metadata = $this->driver->loadMetadataForClass($class);

        $expected = new ClassMetadata('Symfony\Tests\Component\Validator\Fixtures\Entity');
        $expected->addConstraint(new ConstraintA());
        $expected->addConstraint(new ConstraintB());
        $expected->addPropertyConstraint('firstName', new NotNull());
        $expected->addPropertyConstraint('firstName', new Min(3));
        $expected->addPropertyConstraint('firstName', new Choice(array('A', 'B')));
        $expected->addPropertyConstraint('firstName', new All(array(new NotNull(), new Min(3))));
        $expected->addPropertyConstraint('firstName', new All(array('constraints' => array(new NotNull(), new Min(3)))));
        $expected->addPropertyConstraint('firstName', new Collection(array('fields' => array(
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
