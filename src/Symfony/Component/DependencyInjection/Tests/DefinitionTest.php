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

class DefinitionTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $def = new Definition('stdClass');
        $this->assertEquals('stdClass', $def->getClass(), '__construct() takes the class name as its first argument');

        $def = new Definition('stdClass', array('foo'));
        $this->assertEquals(array('foo'), $def->getArguments(), '__construct() takes an optional array of arguments as its second argument');
    }

    public function testSetGetFactory()
    {
        $def = new Definition('stdClass');

        $this->assertSame($def, $def->setFactory('foo'), '->setFactory() implements a fluent interface');
        $this->assertEquals('foo', $def->getFactory(), '->getFactory() returns the factory');

        $def->setFactory('Foo::bar');
        $this->assertEquals(array('Foo', 'bar'), $def->getFactory(), '->setFactory() converts string static method call to the array');
    }

    public function testSetGetClass()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->setClass('foo'), '->setClass() implements a fluent interface');
        $this->assertEquals('foo', $def->getClass(), '->getClass() returns the class name');
    }

    public function testSetGetDecoratedService()
    {
        $def = new Definition('stdClass');
        $this->assertNull($def->getDecoratedService());
        $def->setDecoratedService('foo', 'foo.renamed', 5);
        $this->assertEquals(array('foo', 'foo.renamed', 5), $def->getDecoratedService());
        $def->setDecoratedService(null);
        $this->assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');
        $this->assertNull($def->getDecoratedService());
        $def->setDecoratedService('foo', 'foo.renamed');
        $this->assertEquals(array('foo', 'foo.renamed', 0), $def->getDecoratedService());
        $def->setDecoratedService(null);
        $this->assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');
        $def->setDecoratedService('foo');
        $this->assertEquals(array('foo', null, 0), $def->getDecoratedService());
        $def->setDecoratedService(null);
        $this->assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');
        $this->setExpectedException('InvalidArgumentException', 'The decorated service inner name for "foo" must be different than the service name itself.');
        $def->setDecoratedService('foo', 'foo');
    }

    public function testArguments()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->setArguments(array('foo')), '->setArguments() implements a fluent interface');
        $this->assertEquals(array('foo'), $def->getArguments(), '->getArguments() returns the arguments');
        $this->assertSame($def, $def->addArgument('bar'), '->addArgument() implements a fluent interface');
        $this->assertEquals(array('foo', 'bar'), $def->getArguments(), '->addArgument() adds an argument');
    }

    public function testMethodCalls()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->setMethodCalls(array(array('foo', array('foo')))), '->setMethodCalls() implements a fluent interface');
        $this->assertEquals(array(array('foo', array('foo'))), $def->getMethodCalls(), '->getMethodCalls() returns the methods to call');
        $this->assertSame($def, $def->addMethodCall('bar', array('bar')), '->addMethodCall() implements a fluent interface');
        $this->assertEquals(array(array('foo', array('foo')), array('bar', array('bar'))), $def->getMethodCalls(), '->addMethodCall() adds a method to call');
        $this->assertTrue($def->hasMethodCall('bar'), '->hasMethodCall() returns true if first argument is a method to call registered');
        $this->assertFalse($def->hasMethodCall('no_registered'), '->hasMethodCall() returns false if first argument is not a method to call registered');
        $this->assertSame($def, $def->removeMethodCall('bar'), '->removeMethodCall() implements a fluent interface');
        $this->assertEquals(array(array('foo', array('foo'))), $def->getMethodCalls(), '->removeMethodCall() removes a method to call');
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Method name cannot be empty.
     */
    public function testExceptionOnEmptyMethodCall()
    {
        $def = new Definition('stdClass');
        $def->addMethodCall('');
    }

    public function testSetGetFile()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->setFile('foo'), '->setFile() implements a fluent interface');
        $this->assertEquals('foo', $def->getFile(), '->getFile() returns the file to include');
    }

    public function testSetIsShared()
    {
        $def = new Definition('stdClass');
        $this->assertTrue($def->isShared(), '->isShared() returns true by default');
        $this->assertSame($def, $def->setShared(false), '->setShared() implements a fluent interface');
        $this->assertFalse($def->isShared(), '->isShared() returns false if the instance must not be shared');
    }

    public function testSetIsPublic()
    {
        $def = new Definition('stdClass');
        $this->assertTrue($def->isPublic(), '->isPublic() returns true by default');
        $this->assertSame($def, $def->setPublic(false), '->setPublic() implements a fluent interface');
        $this->assertFalse($def->isPublic(), '->isPublic() returns false if the instance must not be public.');
    }

    public function testSetIsSynthetic()
    {
        $def = new Definition('stdClass');
        $this->assertFalse($def->isSynthetic(), '->isSynthetic() returns false by default');
        $this->assertSame($def, $def->setSynthetic(true), '->setSynthetic() implements a fluent interface');
        $this->assertTrue($def->isSynthetic(), '->isSynthetic() returns true if the service is synthetic.');
    }

    public function testSetIsLazy()
    {
        $def = new Definition('stdClass');
        $this->assertFalse($def->isLazy(), '->isLazy() returns false by default');
        $this->assertSame($def, $def->setLazy(true), '->setLazy() implements a fluent interface');
        $this->assertTrue($def->isLazy(), '->isLazy() returns true if the service is lazy.');
    }

    public function testSetIsAbstract()
    {
        $def = new Definition('stdClass');
        $this->assertFalse($def->isAbstract(), '->isAbstract() returns false by default');
        $this->assertSame($def, $def->setAbstract(true), '->setAbstract() implements a fluent interface');
        $this->assertTrue($def->isAbstract(), '->isAbstract() returns true if the instance must not be public.');
    }

    public function testSetIsDeprecated()
    {
        $def = new Definition('stdClass');
        $this->assertFalse($def->isDeprecated(), '->isDeprecated() returns false by default');
        $this->assertSame($def, $def->setDeprecated(true), '->setDeprecated() implements a fluent interface');
        $this->assertTrue($def->isDeprecated(), '->isDeprecated() returns true if the instance should not be used anymore.');
        $this->assertSame('The "deprecated_service" service is deprecated. You should stop using it, as it will soon be removed.', $def->getDeprecationMessage('deprecated_service'), '->getDeprecationMessage() should return a formatted message template');
    }

    /**
     * @dataProvider invalidDeprecationMessageProvider
     * @expectedException \Symfony\Component\DependencyInjection\Exception\InvalidArgumentException
     */
    public function testSetDeprecatedWithInvalidDeprecationTemplate($message)
    {
        $def = new Definition('stdClass');
        $def->setDeprecated(false, $message);
    }

    public function invalidDeprecationMessageProvider()
    {
        return array(
            "With \rs" => array("invalid \r message %service_id%"),
            "With \ns" => array("invalid \n message %service_id%"),
            'With */s' => array('invalid */ message %service_id%'),
            'message not containing require %service_id% variable' => array('this is deprecated'),
        );
    }

    public function testSetGetConfigurator()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->setConfigurator('foo'), '->setConfigurator() implements a fluent interface');
        $this->assertEquals('foo', $def->getConfigurator(), '->getConfigurator() returns the configurator');
    }

    public function testClearTags()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->clearTags(), '->clearTags() implements a fluent interface');
        $def->addTag('foo', array('foo' => 'bar'));
        $def->clearTags();
        $this->assertEquals(array(), $def->getTags(), '->clearTags() removes all current tags');
    }

    public function testClearTag()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->clearTags(), '->clearTags() implements a fluent interface');
        $def->addTag('1foo1', array('foo1' => 'bar1'));
        $def->addTag('2foo2', array('foo2' => 'bar2'));
        $def->addTag('3foo3', array('foo3' => 'bar3'));
        $def->clearTag('2foo2');
        $this->assertTrue($def->hasTag('1foo1'));
        $this->assertFalse($def->hasTag('2foo2'));
        $this->assertTrue($def->hasTag('3foo3'));
        $def->clearTag('1foo1');
        $this->assertFalse($def->hasTag('1foo1'));
        $this->assertTrue($def->hasTag('3foo3'));
    }

    public function testTags()
    {
        $def = new Definition('stdClass');
        $this->assertEquals(array(), $def->getTag('foo'), '->getTag() returns an empty array if the tag is not defined');
        $this->assertFalse($def->hasTag('foo'));
        $this->assertSame($def, $def->addTag('foo'), '->addTag() implements a fluent interface');
        $this->assertTrue($def->hasTag('foo'));
        $this->assertEquals(array(array()), $def->getTag('foo'), '->getTag() returns attributes for a tag name');
        $def->addTag('foo', array('foo' => 'bar'));
        $this->assertEquals(array(array(), array('foo' => 'bar')), $def->getTag('foo'), '->addTag() can adds the same tag several times');
        $def->addTag('bar', array('bar' => 'bar'));
        $this->assertEquals($def->getTags(), array(
            'foo' => array(array(), array('foo' => 'bar')),
            'bar' => array(array('bar' => 'bar')),
        ), '->getTags() returns all tags');
    }

    public function testSetArgument()
    {
        $def = new Definition('stdClass');

        $def->addArgument('foo');
        $this->assertSame(array('foo'), $def->getArguments());

        $this->assertSame($def, $def->replaceArgument(0, 'moo'));
        $this->assertSame(array('moo'), $def->getArguments());

        $def->addArgument('moo');
        $def
            ->replaceArgument(0, 'foo')
            ->replaceArgument(1, 'bar')
        ;
        $this->assertSame(array('foo', 'bar'), $def->getArguments());
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetArgumentShouldCheckBounds()
    {
        $def = new Definition('stdClass');

        $def->addArgument('foo');
        $def->getArgument(1);
    }

    /**
     * @expectedException \OutOfBoundsException
     * @expectedExceptionMessage The index "1" is not in the range [0, 0].
     */
    public function testReplaceArgumentShouldCheckBounds()
    {
        $def = new Definition('stdClass');

        $def->addArgument('foo');
        $def->replaceArgument(1, 'bar');
    }

    /**
     * @expectedException \OutOfBoundsException
     * @expectedExceptionMessage Cannot replace arguments if none have been configured yet.
     */
    public function testReplaceArgumentWithoutExistingArgumentsShouldCheckBounds()
    {
        $def = new Definition('stdClass');
        $def->replaceArgument(0, 'bar');
    }

    public function testSetGetProperties()
    {
        $def = new Definition('stdClass');

        $this->assertEquals(array(), $def->getProperties());
        $this->assertSame($def, $def->setProperties(array('foo' => 'bar')));
        $this->assertEquals(array('foo' => 'bar'), $def->getProperties());
    }

    public function testSetProperty()
    {
        $def = new Definition('stdClass');

        $this->assertEquals(array(), $def->getProperties());
        $this->assertSame($def, $def->setProperty('foo', 'bar'));
        $this->assertEquals(array('foo' => 'bar'), $def->getProperties());
    }

    public function testAutowired()
    {
        $def = new Definition('stdClass');
        $this->assertFalse($def->isAutowired());
        $def->setAutowired(true);
        $this->assertTrue($def->isAutowired());
    }

    public function testTypes()
    {
        $def = new Definition('stdClass');

        $this->assertEquals(array(), $def->getAutowiringTypes());
        $this->assertSame($def, $def->setAutowiringTypes(array('Foo')));
        $this->assertEquals(array('Foo'), $def->getAutowiringTypes());
        $this->assertSame($def, $def->addAutowiringType('Bar'));
        $this->assertTrue($def->hasAutowiringType('Bar'));
        $this->assertSame($def, $def->removeAutowiringType('Foo'));
        $this->assertEquals(array('Bar'), $def->getAutowiringTypes());
    }
}
