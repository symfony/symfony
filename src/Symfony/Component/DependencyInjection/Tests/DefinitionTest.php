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
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

class DefinitionTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testConstructor()
    {
        $def = new Definition('stdClass');
        self::assertEquals('stdClass', $def->getClass(), '__construct() takes the class name as its first argument');
        self::assertSame(['class' => true], $def->getChanges());

        $def = new Definition('stdClass', ['foo']);
        self::assertEquals(['foo'], $def->getArguments(), '__construct() takes an optional array of arguments as its second argument');
    }

    public function testSetGetFactory()
    {
        $def = new Definition();

        self::assertSame($def, $def->setFactory('foo'), '->setFactory() implements a fluent interface');
        self::assertEquals('foo', $def->getFactory(), '->getFactory() returns the factory');

        $def->setFactory('Foo::bar');
        self::assertEquals(['Foo', 'bar'], $def->getFactory(), '->setFactory() converts string static method call to the array');

        $def->setFactory($ref = new Reference('baz'));
        self::assertSame([$ref, '__invoke'], $def->getFactory(), '->setFactory() converts service reference to class invoke call');
        self::assertSame(['factory' => true], $def->getChanges());
    }

    public function testSetGetClass()
    {
        $def = new Definition('stdClass');
        self::assertSame($def, $def->setClass('foo'), '->setClass() implements a fluent interface');
        self::assertEquals('foo', $def->getClass(), '->getClass() returns the class name');
    }

    public function testSetGetDecoratedService()
    {
        $def = new Definition('stdClass');
        self::assertNull($def->getDecoratedService());
        $def->setDecoratedService('foo', 'foo.renamed', 5, ContainerInterface::NULL_ON_INVALID_REFERENCE);
        self::assertEquals(['foo', 'foo.renamed', 5, ContainerInterface::NULL_ON_INVALID_REFERENCE], $def->getDecoratedService());
        $def->setDecoratedService(null);
        self::assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');
        self::assertNull($def->getDecoratedService());
        $def->setDecoratedService('foo', 'foo.renamed', 5);
        self::assertEquals(['foo', 'foo.renamed', 5], $def->getDecoratedService());
        $def->setDecoratedService(null);
        self::assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');
        self::assertNull($def->getDecoratedService());
        $def->setDecoratedService('foo', 'foo.renamed');
        self::assertEquals(['foo', 'foo.renamed', 0], $def->getDecoratedService());
        $def->setDecoratedService(null);
        self::assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');
        $def->setDecoratedService('foo');
        self::assertEquals(['foo', null, 0], $def->getDecoratedService());
        $def->setDecoratedService(null);
        self::assertNull($def->getDecoratedService());

        $def = new Definition('stdClass');

        self::expectException(\InvalidArgumentException::class);
        self::expectExceptionMessage('The decorated service inner name for "foo" must be different than the service name itself.');

        $def->setDecoratedService('foo', 'foo');
    }

    public function testArguments()
    {
        $def = new Definition('stdClass');
        self::assertSame($def, $def->setArguments(['foo']), '->setArguments() implements a fluent interface');
        self::assertEquals(['foo'], $def->getArguments(), '->getArguments() returns the arguments');
        self::assertSame($def, $def->addArgument('bar'), '->addArgument() implements a fluent interface');
        self::assertEquals(['foo', 'bar'], $def->getArguments(), '->addArgument() adds an argument');
    }

    public function testMethodCalls()
    {
        $def = new Definition('stdClass');
        self::assertSame($def, $def->setMethodCalls([['foo', ['foo']]]), '->setMethodCalls() implements a fluent interface');
        self::assertEquals([['foo', ['foo']]], $def->getMethodCalls(), '->getMethodCalls() returns the methods to call');
        self::assertSame($def, $def->addMethodCall('bar', ['bar']), '->addMethodCall() implements a fluent interface');
        self::assertEquals([['foo', ['foo']], ['bar', ['bar']]], $def->getMethodCalls(), '->addMethodCall() adds a method to call');
        self::assertSame($def, $def->addMethodCall('foobar', ['foobar'], true), '->addMethodCall() implements a fluent interface with third parameter');
        self::assertEquals([['foo', ['foo']], ['bar', ['bar']], ['foobar', ['foobar'], true]], $def->getMethodCalls(), '->addMethodCall() adds a method to call');
        self::assertTrue($def->hasMethodCall('bar'), '->hasMethodCall() returns true if first argument is a method to call registered');
        self::assertFalse($def->hasMethodCall('no_registered'), '->hasMethodCall() returns false if first argument is not a method to call registered');
        self::assertSame($def, $def->removeMethodCall('bar'), '->removeMethodCall() implements a fluent interface');
        self::assertTrue($def->hasMethodCall('foobar'), '->hasMethodCall() returns true if first argument is a method to call registered');
        self::assertSame($def, $def->removeMethodCall('foobar'), '->removeMethodCall() implements a fluent interface');
        self::assertEquals([['foo', ['foo']]], $def->getMethodCalls(), '->removeMethodCall() removes a method to call');
        self::assertSame($def, $def->setMethodCalls([['foobar', ['foobar'], true]]), '->setMethodCalls() implements a fluent interface with third parameter');
        self::assertEquals([['foobar', ['foobar'], true]], $def->getMethodCalls(), '->addMethodCall() adds a method to call');
    }

    public function testExceptionOnEmptyMethodCall()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Method name cannot be empty.');
        $def = new Definition('stdClass');
        $def->addMethodCall('');
    }

    public function testSetGetFile()
    {
        $def = new Definition('stdClass');
        self::assertSame($def, $def->setFile('foo'), '->setFile() implements a fluent interface');
        self::assertEquals('foo', $def->getFile(), '->getFile() returns the file to include');
    }

    public function testSetIsShared()
    {
        $def = new Definition('stdClass');
        self::assertTrue($def->isShared(), '->isShared() returns true by default');
        self::assertSame($def, $def->setShared(false), '->setShared() implements a fluent interface');
        self::assertFalse($def->isShared(), '->isShared() returns false if the instance must not be shared');
    }

    public function testSetIsPublic()
    {
        $def = new Definition('stdClass');
        self::assertFalse($def->isPublic(), '->isPublic() returns false by default');
        self::assertSame($def, $def->setPublic(true), '->setPublic() implements a fluent interface');
        self::assertTrue($def->isPublic(), '->isPublic() returns true if the service is public.');
    }

    public function testSetIsSynthetic()
    {
        $def = new Definition('stdClass');
        self::assertFalse($def->isSynthetic(), '->isSynthetic() returns false by default');
        self::assertSame($def, $def->setSynthetic(true), '->setSynthetic() implements a fluent interface');
        self::assertTrue($def->isSynthetic(), '->isSynthetic() returns true if the service is synthetic.');
    }

    public function testSetIsLazy()
    {
        $def = new Definition('stdClass');
        self::assertFalse($def->isLazy(), '->isLazy() returns false by default');
        self::assertSame($def, $def->setLazy(true), '->setLazy() implements a fluent interface');
        self::assertTrue($def->isLazy(), '->isLazy() returns true if the service is lazy.');
    }

    public function testSetIsAbstract()
    {
        $def = new Definition('stdClass');
        self::assertFalse($def->isAbstract(), '->isAbstract() returns false by default');
        self::assertSame($def, $def->setAbstract(true), '->setAbstract() implements a fluent interface');
        self::assertTrue($def->isAbstract(), '->isAbstract() returns true if the instance must not be public.');
    }

    public function testSetIsDeprecated()
    {
        $def = new Definition('stdClass');
        self::assertFalse($def->isDeprecated(), '->isDeprecated() returns false by default');
        self::assertSame($def, $def->setDeprecated('vendor/package', '1.1', '%service_id%'), '->setDeprecated() implements a fluent interface');
        self::assertTrue($def->isDeprecated(), '->isDeprecated() returns true if the instance should not be used anymore.');

        $deprecation = $def->getDeprecation('deprecated_service');
        self::assertSame('deprecated_service', $deprecation['message'], '->getDeprecation() should return an array with the formatted message template');
        self::assertSame('vendor/package', $deprecation['package']);
        self::assertSame('1.1', $deprecation['version']);
    }

    /**
     * @group legacy
     */
    public function testSetDeprecatedWithoutPackageAndVersion()
    {
        $this->expectDeprecation('Since symfony/dependency-injection 5.1: The signature of method "Symfony\Component\DependencyInjection\Definition::setDeprecated()" requires 3 arguments: "string $package, string $version, string $message", not defining them is deprecated.');

        $def = new Definition('stdClass');
        $def->setDeprecated(true, '%service_id%');

        $deprecation = $def->getDeprecation('deprecated_service');
        self::assertSame('deprecated_service', $deprecation['message']);
        self::assertSame('', $deprecation['package']);
        self::assertSame('', $deprecation['version']);
    }

    /**
     * @dataProvider invalidDeprecationMessageProvider
     */
    public function testSetDeprecatedWithInvalidDeprecationTemplate($message)
    {
        self::expectException(InvalidArgumentException::class);
        $def = new Definition('stdClass');
        $def->setDeprecated('vendor/package', '1.1', $message);
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
        self::assertSame($def, $def->setConfigurator('foo'), '->setConfigurator() implements a fluent interface');
        self::assertEquals('foo', $def->getConfigurator(), '->getConfigurator() returns the configurator');
    }

    public function testClearTags()
    {
        $def = new Definition('stdClass');
        self::assertSame($def, $def->clearTags(), '->clearTags() implements a fluent interface');
        $def->addTag('foo', ['foo' => 'bar']);
        $def->clearTags();
        self::assertEquals([], $def->getTags(), '->clearTags() removes all current tags');
    }

    public function testClearTag()
    {
        $def = new Definition('stdClass');
        self::assertSame($def, $def->clearTags(), '->clearTags() implements a fluent interface');
        $def->addTag('1foo1', ['foo1' => 'bar1']);
        $def->addTag('2foo2', ['foo2' => 'bar2']);
        $def->addTag('3foo3', ['foo3' => 'bar3']);
        $def->clearTag('2foo2');
        self::assertTrue($def->hasTag('1foo1'));
        self::assertFalse($def->hasTag('2foo2'));
        self::assertTrue($def->hasTag('3foo3'));
        $def->clearTag('1foo1');
        self::assertFalse($def->hasTag('1foo1'));
        self::assertTrue($def->hasTag('3foo3'));
    }

    public function testTags()
    {
        $def = new Definition('stdClass');
        self::assertEquals([], $def->getTag('foo'), '->getTag() returns an empty array if the tag is not defined');
        self::assertFalse($def->hasTag('foo'));
        self::assertSame($def, $def->addTag('foo'), '->addTag() implements a fluent interface');
        self::assertTrue($def->hasTag('foo'));
        self::assertEquals([[]], $def->getTag('foo'), '->getTag() returns attributes for a tag name');
        $def->addTag('foo', ['foo' => 'bar']);
        self::assertEquals([[], ['foo' => 'bar']], $def->getTag('foo'), '->addTag() can adds the same tag several times');
        $def->addTag('bar', ['bar' => 'bar']);
        self::assertEquals([
            'foo' => [[], ['foo' => 'bar']],
            'bar' => [['bar' => 'bar']],
        ], $def->getTags(), '->getTags() returns all tags');
    }

    public function testSetArgument()
    {
        $def = new Definition('stdClass');

        $def->addArgument('foo');
        self::assertSame(['foo'], $def->getArguments());

        self::assertSame($def, $def->replaceArgument(0, 'moo'));
        self::assertSame(['moo'], $def->getArguments());

        $def->addArgument('moo');
        $def
            ->replaceArgument(0, 'foo')
            ->replaceArgument(1, 'bar')
        ;
        self::assertSame(['foo', 'bar'], $def->getArguments());
    }

    public function testGetArgumentShouldCheckBounds()
    {
        self::expectException(\OutOfBoundsException::class);
        $def = new Definition('stdClass');

        $def->addArgument('foo');
        $def->getArgument(1);
    }

    public function testReplaceArgumentShouldCheckBounds()
    {
        self::expectException(\OutOfBoundsException::class);
        self::expectExceptionMessage('The index "1" is not in the range [0, 0] of the arguments of class "stdClass".');
        $def = new Definition('stdClass');

        $def->addArgument('foo');
        $def->replaceArgument(1, 'bar');
    }

    public function testReplaceArgumentWithoutExistingArgumentsShouldCheckBounds()
    {
        self::expectException(\OutOfBoundsException::class);
        self::expectExceptionMessage('Cannot replace arguments for class "stdClass" if none have been configured yet.');
        $def = new Definition('stdClass');
        $def->replaceArgument(0, 'bar');
    }

    public function testSetGetProperties()
    {
        $def = new Definition('stdClass');

        self::assertEquals([], $def->getProperties());
        self::assertSame($def, $def->setProperties(['foo' => 'bar']));
        self::assertEquals(['foo' => 'bar'], $def->getProperties());
    }

    public function testSetProperty()
    {
        $def = new Definition('stdClass');

        self::assertEquals([], $def->getProperties());
        self::assertSame($def, $def->setProperty('foo', 'bar'));
        self::assertEquals(['foo' => 'bar'], $def->getProperties());
    }

    public function testAutowired()
    {
        $def = new Definition('stdClass');
        self::assertFalse($def->isAutowired());

        $def->setAutowired(true);
        self::assertTrue($def->isAutowired());

        $def->setAutowired(false);
        self::assertFalse($def->isAutowired());
    }

    public function testChangesNoChanges()
    {
        $def = new Definition();

        self::assertSame([], $def->getChanges());
    }

    public function testGetChangesWithChanges()
    {
        $def = new Definition('stdClass', ['fooarg']);

        $def->setAbstract(true);
        $def->setAutowired(true);
        $def->setConfigurator('configuration_func');
        $def->setDecoratedService(null);
        $def->setDeprecated('vendor/package', '1.1', '%service_id%');
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

        self::assertSame([
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
        self::assertSame([], $def->getChanges());
    }

    public function testShouldAutoconfigure()
    {
        $def = new Definition('stdClass');
        self::assertFalse($def->isAutoconfigured());
        $def->setAutoconfigured(true);
        self::assertTrue($def->isAutoconfigured());
    }

    public function testAddError()
    {
        $def = new Definition('stdClass');
        self::assertFalse($def->hasErrors());
        $def->addError('First error');
        $def->addError('Second error');
        self::assertSame(['First error', 'Second error'], $def->getErrors());
    }

    public function testMultipleMethodCalls()
    {
        $def = new Definition('stdClass');

        $def->addMethodCall('configure', ['arg1']);
        self::assertTrue($def->hasMethodCall('configure'));
        self::assertCount(1, $def->getMethodCalls());

        $def->addMethodCall('configure', ['arg2']);
        self::assertTrue($def->hasMethodCall('configure'));
        self::assertCount(2, $def->getMethodCalls());

        $def->removeMethodCall('configure');
        self::assertFalse($def->hasMethodCall('configure'));
    }
}
