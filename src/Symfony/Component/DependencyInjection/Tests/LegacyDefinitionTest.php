<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests;

use Symfony\Component\DependencyInjection\Definition;

/**
 * @group legacy
 */
class LegacyDefinitionTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);
    }

    public function testSetGetFactoryClass()
    {
        $def = new Definition('stdClass');
        $this->assertNull($def->getFactoryClass());
        $this->assertSame($def, $def->setFactoryClass('stdClass2'), "->setFactoryClass() implements a fluent interface.");
        $this->assertEquals('stdClass2', $def->getFactoryClass(), "->getFactoryClass() returns current class to construct this service.");
    }

    public function testSetGetFactoryMethod()
    {
        $def = new Definition('stdClass');
        $this->assertNull($def->getFactoryMethod());
        $this->assertSame($def, $def->setFactoryMethod('foo'), '->setFactoryMethod() implements a fluent interface');
        $this->assertEquals('foo', $def->getFactoryMethod(), '->getFactoryMethod() returns the factory method name');
    }

    public function testSetGetFactoryService()
    {
        $def = new Definition('stdClass');
        $this->assertNull($def->getFactoryService());
        $this->assertSame($def, $def->setFactoryService('foo.bar'), "->setFactoryService() implements a fluent interface.");
        $this->assertEquals('foo.bar', $def->getFactoryService(), "->getFactoryService() returns current service to construct this service.");
    }
}
