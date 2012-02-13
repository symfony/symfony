<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\CacheBundle\Tests\DependencyInjection\Backend;

class BackendFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideBackendFactoryClassName
     */
    public function testGetName($className, $valid, $name = null)
    {
        $factory = $this->getBackendFactoryMock($className);
        $getName = new \ReflectionMethod($factory, 'getName');
        $getName->setAccessible(true);
        try {
            $this->assertEquals($name, $getName->invoke($factory));
            $this->assertTrue($valid);
        } catch (\LogicException $e) {
            $this->assertFalse($valid);
        }
    }

    public function provideBackendFactoryClassName()
    {
        return array(
            array('FooBackendFactory', true, 'Foo'),
            array('Foo', false),
        );
    }

    /**
     * @dataProvider provideNameTypeKey
     */
    public function testGenerateConfigKey($name, $type, $key)
    {
        $factory = $this->getBackendFactoryMock(null, $name);

        $factory
            ->expects($this->once())
            ->method('getName')
            ->will($this->returnValue($name))
        ;

        $factory->getType();

        //$this->assertEquals($key, $factory->getConfigKey());
    }

    /**
     * @dataProvider provideNameTypeKey
     */
    public function testGenerateType($name, $type, $key)
    {
        $factory = $this->getBackendFactoryMock(null, $name);

        $factory
            ->expects($this->once())
            ->method('getName')
            ->will($this->returnValue($name))
        ;

        $this->assertEquals($type, $factory->getType());
    }

    public function provideNameTypeKey()
    {
        return array(
            array('Foo', 'foo', 'foo'),
            array('FooBar', 'foobar', 'foo_bar'),
            array('Foo Bar', 'foo bar', 'foo_bar'),
            array('Foo bar', 'foo bar', 'foobar'),
        );
    }

    protected function getBackendFactoryMock($className = null, $name = null)
    {
        $methods = array('addConfiguration', 'createService');
        if ($name) {
            $methods[] = 'getName';
        }

        $factory = $this
            ->getMockBuilder('Symfony\\Bundle\\CacheBundle\\DependencyInjection\\Backend\\AbstractBackendFactory')
            ->setMethods($methods)
        ;

        if ($className) {
            $factory->setMockClassName($className);
        }

        return $factory->getMock();
    }
}