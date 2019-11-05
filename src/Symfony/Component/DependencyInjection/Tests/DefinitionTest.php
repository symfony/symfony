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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class DefinitionTest extends TestCase
{
    public function testConstructor()
    {
        $def = new Definition('stdClass');
        $this->assertEquals('stdClass', $def->getClass(), '__construct() takes the class name as its first argument');
        $this->assertSame(['class' => true], $def->getChanges());

        $def = new Definition('stdClass', ['foo']);
        $this->assertEquals(['foo'], $def->getArguments(), '__construct() takes an optional array of arguments as its second argument');
    }

    public function testSetGetFactory()
    {
        $def = new Definition();

        $this->assertSame($def, $def->setFactory('foo'), '->setFactory() implements a fluent interface');
        $this->assertEquals('foo', $def->getFactory(), '->getFactory() returns the factory');

        $def->setFactory('Foo::bar');
        $this->assertEquals(['Foo', 'bar'], $def->getFactory(), '->setFactory() converts string static method call to the array');

        $def->setFactory($ref = new Reference('baz'));
        $this->assertSame([$ref, '__invoke'], $def->getFactory(), '->setFactory() converts service reference to class invoke call');
        $this->assertSame(['factory' => true], $def->getChanges());
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
        $def->setDecoratedService('foo', 'foo.renamed', 5, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->assertEquals(['foo', 'foo.renamed', 5, ContainerInterface::NULL_ON_INVALID_REFERENCE], $def->getDecoratedService());
        $def->setDecoratedService(null);
        $this->assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');
        $this->assertNull($def->getDecoratedService());
        $def->setDecoratedService('foo', 'foo.renamed', 5);
        $this->assertEquals(['foo', 'foo.renamed', 5], $def->getDecoratedService());
        $def->setDecoratedService(null);
        $this->assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');
        $this->assertNull($def->getDecoratedService());
        $def->setDecoratedService('foo', 'foo.renamed');
        $this->assertEquals(['foo', 'foo.renamed', 0], $def->getDecoratedService());
        $def->setDecoratedService(null);
        $this->assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');
        $def->setDecoratedService('foo');
        $this->assertEquals(['foo', null, 0], $def->getDecoratedService());
        $def->setDecoratedService(null);
        $this->assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');

        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('The decorated service inner name for "foo" must be different than the service name itself.');

        $def->setDecoratedService('foo', 'foo');
    }

    public function testArguments()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->setArguments(['foo']), '->setArguments() implements a fluent interface');
        $this->assertEquals(['foo'], $def->getArguments(), '->getArguments() returns the arguments');
        $this->assertSame($def, $def->addArgument('bar'), '->addArgument() implements a fluent interface');
        $this->assertEquals(['foo', 'bar'], $def->getArguments(), '->addArgument() adds an argument');
    }

    public function testMethodCalls()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->setMethodCalls([['foo', ['foo']]]), '->setMethodCalls() implements a fluent interface');
        $this->assertEquals([['foo', ['foo']]], $def->getMethodCalls(), '->getMethodCalls() returns the methods to call');
        $this->assertSame($def, $def->addMethodCall('bar', ['bar']), '->addMethodCall() implements a fluent interface');
        $this->assertEquals([['foo', ['foo']], ['bar', ['bar']]], $def->getMethodCalls(), '->addMethodCall() adds a method to call');
        $this->assertSame($def, $def->addMethodCall('foobar', ['foobar'], true), '->addMethodCall() implements a fluent interface with third parameter');
        $this->assertEquals([['foo', ['foo']], ['bar', ['bar']], ['foobar', ['foobar'], true]], $def->getMethodCalls(), '->addMethodCall() adds a method to call');
        $this->assertTrue($def->hasMethodCall('bar'), '->hasMethodCall() returns true if first argument is a method to call registered');
        $this->assertFalse($def->hasMethodCall('no_registered'), '->hasMethodCall() returns false if first argument is not a method to call registered');
        $this->assertSame($def, $def->removeMethodCall('bar'), '->removeMethodCall() implements a fluent interface');
        $this->assertTrue($def->hasMethodCall('foobar'), '->hasMethodCall() returns true if first argument is a method to call registered');
        $this->assertSame($def, $def->removeMethodCall('foobar'), '->removeMethodCall() implements a fluent interface');
        $this->assertEquals([['foo', ['foo']]], $def->getMethodCalls(), '->removeMethodCall() removes a method to call');
        $this->assertSame($def, $def->setMethodCalls([['foobar', ['foobar'], true]]), '->setMethodCalls() implements a fluent interface with third parameter');
        $this->assertEquals([['foobar', ['foobar'], true]], $def->getMethodCalls(), '->addMethodCall() adds a method to call');
    }

    public function testExceptionOnEmptyMethodCall()
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $this->expectExceptionMessage('Method name cannot be empty.');
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

        $def->setDeprecated(true, '%service_id%');
        $this->assertSame('deprecated_service', $def->getDeprecationMessage('deprecated_service'), '->getDeprecationMessage() should return given formatted message template');
    }

    /**
     * @dataProvider invalidDeprecationMessageProvider
     */
    public function testSetDeprecatedWithInvalidDeprecationTemplate($message)
    {
        $this->expectException('Symfony\Component\DependencyInjection\Exception\InvalidArgumentException');
        $def = new Definition('stdClass');
        $def->setDeprecated(false, $message);
    }

    public function invalidDeprecationMessageProvider()
    {
        return [
            "With \rs" => ["invalid \r message %service_id%"],
            "With \ns" => ["invalid \n message %service_id%"],
            'With */s' => ['invalid */ message %service_id%'],
            'message not containing require %service_id% variable' => ['this is deprecated'],
            'template not containing require %service_id% variable' => [true],
        ];
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
        $def->addTag('foo', ['foo' => 'bar']);
        $def->clearTags();
        $this->assertEquals([], $def->getTags(), '->clearTags() removes all current tags');
    }

    public function testClearTag()
    {
        $def = new Definition('stdClass');
        $this->assertSame($def, $def->clearTags(), '->clearTags() implements a fluent interface');
        $def->addTag('1foo1', ['foo1' => 'bar1']);
        $def->addTag('2foo2', ['foo2' => 'bar2']);
        $def->addTag('3foo3', ['foo3' => 'bar3']);
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
        $this->assertEquals([], $def->getTag('foo'), '->getTag() returns an empty array if the tag is not defined');
        $this->assertFalse($def->hasTag('foo'));
        $this->assertSame($def, $def->addTag('foo'), '->addTag() implements a fluent interface');
        $this->assertTrue($def->hasTag('foo'));
        $this->assertEquals([[]], $def->getTag('foo'), '->getTag() returns attributes for a tag name');
        $def->addTag('foo', ['foo' => 'bar']);
        $this->assertEquals([[], ['foo' => 'bar']], $def->getTag('foo'), '->addTag() can adds the same tag several times');
        $def->addTag('bar', ['bar' => 'bar']);
        $this->assertEquals($def->getTags(), [
            'foo' => [[], ['foo' => 'bar']],
            'bar' => [['bar' => 'bar']],
        ], '->getTags() returns all tags');
    }

    public function testSetArgument()
    {
        $def = new Definition('stdClass');

        $def->addArgument('foo');
        $this->assertSame(['foo'], $def->getArguments());

        $this->assertSame($def, $def->replaceArgument(0, 'moo'));
        $this->assertSame(['moo'], $def->getArguments());

        $def->addArgument('moo');
        $def
            ->replaceArgument(0, 'foo')
            ->replaceArgument(1, 'bar')
        ;
        $this->assertSame(['foo', 'bar'], $def->getArguments());
    }

    public function testGetArgumentShouldCheckBounds()
    {
        $this->expectException('OutOfBoundsException');
        $def = new Definition('stdClass');

        $def->addArgument('foo');
        $def->getArgument(1);
    }

    public function testReplaceArgumentShouldCheckBounds()
    {
        $this->expectException('OutOfBoundsException');
        $this->expectExceptionMessage('The index "1" is not in the range [0, 0].');
        $def = new Definition('stdClass');

        $def->addArgument('foo');
        $def->replaceArgument(1, 'bar');
    }

    public function testReplaceArgumentWithoutExistingArgumentsShouldCheckBounds()
    {
        $this->expectException('OutOfBoundsException');
        $this->expectExceptionMessage('Cannot replace arguments if none have been configured yet.');
        $def = new Definition('stdClass');
        $def->replaceArgument(0, 'bar');
    }

    public function testSetGetProperties()
    {
        $def = new Definition('stdClass');

        $this->assertEquals([], $def->getProperties());
        $this->assertSame($def, $def->setProperties(['foo' => 'bar']));
        $this->assertEquals(['foo' => 'bar'], $def->getProperties());
    }

    public function testSetProperty()
    {
        $def = new Definition('stdClass');

        $this->assertEquals([], $def->getProperties());
        $this->assertSame($def, $def->setProperty('foo', 'bar'));
        $this->assertEquals(['foo' => 'bar'], $def->getProperties());
    }

    public function testAutowired()
    {
        $def = new Definition('stdClass');
        $this->assertFalse($def->isAutowired());

        $def->setAutowired(true);
        $this->assertTrue($def->isAutowired());

        $def->setAutowired(false);
        $this->assertFalse($def->isAutowired());
    }

    public function testChangesNoChanges()
    {
        $def = new Definition();

        $this->assertSame([], $def->getChanges());
    }

    public function testGetChangesWithChanges()
    {
        $def = new Definition('stdClass', ['fooarg']);

        $def->setAbstract(true);
        $def->setAutowired(true);
        $def->setConfigurator('configuration_func');
        $def->setDecoratedService(null);
        $def->setDeprecated(true);
        $def->setFactory('factory_func');
        $def->setFile('foo.php');
        $def->setLazy(true);
        $def->setPublic(true);
        $def->setShared(true);
        $def->setSynthetic(true);
        // changes aren't tracked for these, class or arguments
        $def->setInstanceofConditionals([]);
        $def->addTag('foo_tag');
        $def->addMethodCall('methodCall');
        $def->setProperty('fooprop', true);
        $def->setAutoconfigured(true);

        $this->assertSame([
            'class' => true,
            'autowired' => true,
            'configurator' => true,
            'decorated_service' => true,
            'deprecated' => true,
            'factory' => true,
            'file' => true,
            'lazy' => true,
            'public' => true,
            'shared' => true,
            'autoconfigured' => true,
        ], $def->getChanges());

        $def->setChanges([]);
        $this->assertSame([], $def->getChanges());
    }

    public function testShouldAutoconfigure()
    {
        $def = new Definition('stdClass');
        $this->assertFalse($def->isAutoconfigured());
        $def->setAutoconfigured(true);
        $this->assertTrue($def->isAutoconfigured());
    }

    public function testAddError()
    {
        $def = new Definition('stdClass');
        $this->assertFalse($def->hasErrors());
        $def->addError('First error');
        $def->addError('Second error');
        $this->assertSame(['First error', 'Second error'], $def->getErrors());
    }
}
