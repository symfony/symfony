<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping\Factory;

use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Factory\LazyLoadingMetadataFactory;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;

class LazyLoadingMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    const CLASS_NAME = 'Symfony\Component\Validator\Tests\Fixtures\Entity';
    const PARENT_CLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityParent';
    const INTERFACE_A_CLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityInterfaceA';
    const INTERFACE_B_CLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityInterfaceB';
    const PARENT_INTERFACE_CLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityParentInterface';

    public function testLoadClassMetadataWithInterface()
    {
        $factory = new LazyLoadingMetadataFactory(new TestLoader());
        $metadata = $factory->getMetadataFor(self::PARENT_CLASS);

        $constraints = array(
            new ConstraintA(array('groups' => array('Default', 'EntityInterfaceA', 'EntityParent'))),
            new ConstraintA(array('groups' => array('Default', 'EntityParent'))),
        );

        $this->assertEquals($constraints, $metadata->getConstraints());
    }

    public function testMergeParentConstraints()
    {
        $factory = new LazyLoadingMetadataFactory(new TestLoader());
        $metadata = $factory->getMetadataFor(self::CLASS_NAME);

        $constraints = array(
            new ConstraintA(array('groups' => array(
                'Default',
                'EntityInterfaceA',
                'EntityParent',
                'Entity',
            ))),
            new ConstraintA(array('groups' => array(
                'Default',
                'EntityParent',
                'Entity',
            ))),
            new ConstraintA(array('groups' => array(
                'Default',
                'EntityParentInterface',
                'EntityInterfaceB',
                'Entity',
            ))),
            new ConstraintA(array('groups' => array(
                'Default',
                'EntityInterfaceB',
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
        $cache = $this->getMock('Symfony\Component\Validator\Mapping\Cache\CacheInterface');
        $factory = new LazyLoadingMetadataFactory(new TestLoader(), $cache);

        $parentClassConstraints = array(
            new ConstraintA(array('groups' => array('Default', 'EntityInterfaceA', 'EntityParent'))),
            new ConstraintA(array('groups' => array('Default', 'EntityParent'))),
        );
        $interfaceAConstraints = array(
            new ConstraintA(array('groups' => array('Default', 'EntityInterfaceA'))),
        );

        $cache->expects($this->never())
              ->method('has');
        $cache->expects($this->exactly(2))
              ->method('read')
              ->withConsecutive(
                  array($this->equalTo(self::PARENT_CLASS)),
                  array($this->equalTo(self::INTERFACE_A_CLASS))
              )
              ->will($this->returnValue(false));
        $cache->expects($this->exactly(2))
              ->method('write')
              ->withConsecutive(
                  $this->callback(function ($metadata) use ($interfaceAConstraints) {
                      return $interfaceAConstraints == $metadata->getConstraints();
                  }),
                  $this->callback(function ($metadata) use ($parentClassConstraints) {
                      return $parentClassConstraints == $metadata->getConstraints();
                  })
              );

        $metadata = $factory->getMetadataFor(self::PARENT_CLASS);

        $this->assertEquals(self::PARENT_CLASS, $metadata->getClassName());
        $this->assertEquals($parentClassConstraints, $metadata->getConstraints());
    }

    public function testReadMetadataFromCache()
    {
        $loader = $this->getMock('Symfony\Component\Validator\Mapping\Loader\LoaderInterface');
        $cache = $this->getMock('Symfony\Component\Validator\Mapping\Cache\CacheInterface');
        $factory = new LazyLoadingMetadataFactory($loader, $cache);

        $metadata = new ClassMetadata(self::PARENT_CLASS);
        $metadata->addConstraint(new ConstraintA());

        $loader->expects($this->never())
               ->method('loadClassMetadata');

        $cache->expects($this->never())
              ->method('has');
        $cache->expects($this->once())
              ->method('read')
              ->will($this->returnValue($metadata));

        $this->assertEquals($metadata, $factory->getMetadataFor(self::PARENT_CLASS));
    }
}

class TestLoader implements LoaderInterface
{
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $metadata->addConstraint(new ConstraintA());
    }
}
