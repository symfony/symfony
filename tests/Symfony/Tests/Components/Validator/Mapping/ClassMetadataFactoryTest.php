<?php

namespace Symfony\Tests\Components\Validator\Mapping;

require_once __DIR__.'/../Fixtures/Entity.php';
require_once __DIR__.'/../Fixtures/ConstraintA.php';
require_once __DIR__.'/../Fixtures/ConstraintB.php';

use Symfony\Tests\Components\Validator\Fixtures\Entity;
use Symfony\Tests\Components\Validator\Fixtures\ConstraintA;
use Symfony\Tests\Components\Validator\Fixtures\ConstraintB;
use Symfony\Components\Validator\Mapping\ClassMetadataFactory;
use Symfony\Components\Validator\Mapping\ClassMetadata;
use Symfony\Components\Validator\Mapping\PropertyMetadata;
use Symfony\Components\Validator\Mapping\Loader\LoaderInterface;

class ClassMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Tests\Components\Validator\Fixtures\Entity';
    const PARENTCLASS = 'Symfony\Tests\Components\Validator\Fixtures\EntityParent';

    public function testLoadClassMetadata()
    {
        $factory = new ClassMetadataFactory(new TestLoader());
        $metadata = $factory->getClassMetadata(self::PARENTCLASS);

        $constraints = array(
            new ConstraintA(array('groups' => array('Default', 'EntityParent'))),
        );

        $this->assertEquals($constraints, $metadata->getConstraints());
    }

    public function testMergeParentConstraints()
    {
        $factory = new ClassMetadataFactory(new TestLoader());
        $metadata = $factory->getClassMetadata(self::CLASSNAME);

        $constraints = array(
            new ConstraintA(array('groups' => array(
                'Default',
                'EntityParent',
                'Entity',
            ))),
            new ConstraintA(array('groups' => array(
                'Default',
                'EntityInterface',
                'Entity',
            ))),
            new ConstraintA(array('groups' => array(
                'Default',
                'Entity',
            ))),
        );

        $this->assertEquals($constraints, $metadata->getConstraints());
    }

    public function testWriteMetadataToCache()
    {
        $cache = $this->getMock('Symfony\Components\Validator\Mapping\Cache\CacheInterface');
        $factory = new ClassMetadataFactory(new TestLoader(), $cache);

        $tester = $this;
        $constraints = array(
            new ConstraintA(array('groups' => array('Default', 'EntityParent'))),
        );

        $cache->expects($this->once())
              ->method('has')
              ->with($this->equalTo(self::PARENTCLASS))
              ->will($this->returnValue(false));
        $cache->expects($this->once())
              ->method('write')
              ->will($this->returnCallback(function($metadata) use ($tester, $constraints) {
                  $tester->assertEquals($constraints, $metadata->getConstraints());
              }));

        $metadata = $factory->getClassMetadata(self::PARENTCLASS);

        $this->assertEquals(self::PARENTCLASS, $metadata->getClassName());
        $this->assertEquals($constraints, $metadata->getConstraints());
    }

    public function testReadMetadataFromCache()
    {
        $loader = $this->getMock('Symfony\Components\Validator\Mapping\Loader\LoaderInterface');
        $cache = $this->getMock('Symfony\Components\Validator\Mapping\Cache\CacheInterface');
        $factory = new ClassMetadataFactory($loader, $cache);

        $tester = $this;
        $metadata = new ClassMetadata(self::PARENTCLASS);
        $metadata->addConstraint(new ConstraintA());

        $loader->expects($this->never())
               ->method('loadClassMetadata');

        $cache->expects($this->once())
              ->method('has')
              ->with($this->equalTo(self::PARENTCLASS))
              ->will($this->returnValue(true));
        $cache->expects($this->once())
              ->method('read')
              ->will($this->returnValue($metadata));

        $this->assertEquals($metadata,$factory->getClassMetadata(self::PARENTCLASS));
    }
}

class TestLoader implements LoaderInterface
{
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $metadata->addConstraint(new ConstraintA());
    }
}
