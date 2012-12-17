<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Mapping;

use Symfony\Component\Validator\Tests\Fixtures\Entity;
use Symfony\Component\Validator\Tests\Fixtures\ConstraintA;
use Symfony\Component\Validator\Mapping\ClassMetadataFactory;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\Loader\LoaderInterface;

class ClassMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    const CLASSNAME = 'Symfony\Component\Validator\Tests\Fixtures\Entity';
    const PARENTCLASS = 'Symfony\Component\Validator\Tests\Fixtures\EntityParent';

    public function handle($errorNumber, $message, $file, $line, $context)
    {
        if ($errorNumber & E_USER_DEPRECATED) {
            return true;
        }

        return \PHPUnit_Util_ErrorHandler::handleError($errorNumber, $message, $file, $line);
    }

    public function testLoadClassMetadata()
    {
        $factory = new ClassMetadataFactory(new TestLoader());
        set_error_handler(array($this, 'handle'));
        $metadata = $factory->getClassMetadata(self::PARENTCLASS);
        restore_error_handler();

        $constraints = array(
            new ConstraintA(array('groups' => array('Default', 'EntityParent'))),
        );

        $this->assertEquals($constraints, $metadata->getConstraints());
    }

    public function testMergeParentConstraints()
    {
        $factory = new ClassMetadataFactory(new TestLoader());
        set_error_handler(array($this, 'handle'));
        $metadata = $factory->getClassMetadata(self::CLASSNAME);
        restore_error_handler();

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
        $cache = $this->getMock('Symfony\Component\Validator\Mapping\Cache\CacheInterface');
        $factory = new ClassMetadataFactory(new TestLoader(), $cache);

        $tester = $this;
        $constraints = array(
            new ConstraintA(array('groups' => array('Default', 'EntityParent'))),
        );

        $cache->expects($this->never())
              ->method('has');
        $cache->expects($this->once())
              ->method('read')
              ->with($this->equalTo(self::PARENTCLASS))
              ->will($this->returnValue(false));
        $cache->expects($this->once())
              ->method('write')
              ->will($this->returnCallback(function($metadata) use ($tester, $constraints) {
                  $tester->assertEquals($constraints, $metadata->getConstraints());
              }));

        set_error_handler(array($this, 'handle'));
        $metadata = $factory->getClassMetadata(self::PARENTCLASS);
        restore_error_handler();

        $this->assertEquals(self::PARENTCLASS, $metadata->getClassName());
        $this->assertEquals($constraints, $metadata->getConstraints());
    }

    public function testReadMetadataFromCache()
    {
        $loader = $this->getMock('Symfony\Component\Validator\Mapping\Loader\LoaderInterface');
        $cache = $this->getMock('Symfony\Component\Validator\Mapping\Cache\CacheInterface');
        $factory = new ClassMetadataFactory($loader, $cache);

        $tester = $this;
        $metadata = new ClassMetadata(self::PARENTCLASS);
        $metadata->addConstraint(new ConstraintA());

        $loader->expects($this->never())
               ->method('loadClassMetadata');

        $cache->expects($this->never())
              ->method('has');
        $cache->expects($this->once())
              ->method('read')
              ->will($this->returnValue($metadata));

        set_error_handler(array($this, 'handle'));
        $this->assertEquals($metadata,$factory->getClassMetadata(self::PARENTCLASS));
        restore_error_handler();
    }
}

class TestLoader implements LoaderInterface
{
    public function loadClassMetadata(ClassMetadata $metadata)
    {
        $metadata->addConstraint(new ConstraintA());
    }
}
