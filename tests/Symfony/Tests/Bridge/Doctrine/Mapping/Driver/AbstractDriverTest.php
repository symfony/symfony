<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Tests\Bridge\Doctrine\Mapping\Driver;

abstract class AbstractDriverTest extends \PHPUnit_Framework_TestCase
{
    public function testFindMappingFile()
    {
        $driver = $this->getDriver(array(
            'MyNamespace\MySubnamespace\EntityFoo' => 'foo',
            'MyNamespace\MySubnamespace\Entity' => $this->dir,
        ));

        touch($filename = $this->dir.'/Foo'.$this->getFileExtension());
        $this->assertEquals($filename, $this->invoke($driver, '_findMappingFile', array('MyNamespace\MySubnamespace\Entity\Foo')));
    }

    public function testFindMappingFileInSubnamespace()
    {
        $driver = $this->getDriver(array(
            'MyNamespace\MySubnamespace\Entity' => $this->dir,
        ));

        touch($filename = $this->dir.'/Foo.Bar'.$this->getFileExtension());
        $this->assertEquals($filename, $this->invoke($driver, '_findMappingFile', array('MyNamespace\MySubnamespace\Entity\Foo\Bar')));
    }

    public function testFindMappingFileNamespacedFoundFileNotFound()
    {
        $this->setExpectedException(
            'Doctrine\ORM\Mapping\MappingException',
            "No mapping file found named '".$this->dir."/Foo".$this->getFileExtension()."' for class 'MyNamespace\MySubnamespace\Entity\Foo'."
        );

        $driver = $this->getDriver(array(
            'MyNamespace\MySubnamespace\Entity' => $this->dir,
        ));

        $this->invoke($driver, '_findMappingFile', array('MyNamespace\MySubnamespace\Entity\Foo'));
    }

    public function testFindMappingNamespaceNotFound()
    {
        $this->setExpectedException(
            'Doctrine\ORM\Mapping\MappingException',
            "No mapping file found named 'Foo".$this->getFileExtension()."' for class 'MyOtherNamespace\MySubnamespace\Entity\Foo'."
        );

        $driver = $this->getDriver(array(
            'MyNamespace\MySubnamespace\Entity' => $this->dir,
        ));

        $this->invoke($driver, '_findMappingFile', array('MyOtherNamespace\MySubnamespace\Entity\Foo'));
    }

    protected function setUp()
    {
        if (!class_exists('Doctrine\\Common\\Version')) {
            $this->markTestSkipped('Doctrine is not available.');
        }

        $this->dir = sys_get_temp_dir().'/abstract_driver_test';
        @mkdir($this->dir, 0777, true);
    }

    protected function tearDown()
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir), \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $path) {
            if ($path->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($this->dir);
    }

    abstract protected function getFileExtension();
    abstract protected function getDriver(array $paths = array());

    private function setField($obj, $field, $value)
    {
        $ref = new \ReflectionProperty($obj, $field);
        $ref->setAccessible(true);
        $ref->setValue($obj, $value);
    }

    private function invoke($obj, $method, array $args = array())
    {
        $ref = new \ReflectionMethod($obj, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($obj, $args);
    }
}
