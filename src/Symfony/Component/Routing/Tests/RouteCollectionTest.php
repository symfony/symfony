<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RouteCollectionTest extends TestCase
{
    public function testRoute()
    {
        $collection = new RouteCollection();
        $route = new Route('/foo');
        $collection->add('foo', $route);
        $this->assertEquals(['foo' => $route], $collection->all(), '->add() adds a route');
        $this->assertEquals($route, $collection->get('foo'), '->get() returns a route by name');
        $this->assertNull($collection->get('bar'), '->get() returns null if a route does not exist');
    }

    public function testOverriddenRoute()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));
        $collection->add('foo', new Route('/foo1'));

        $this->assertEquals('/foo1', $collection->get('foo')->getPath());
    }

    public function testDeepOverriddenRoute()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection1->add('foo', new Route('/foo1'));

        $collection2 = new RouteCollection();
        $collection2->add('foo', new Route('/foo2'));

        $collection1->addCollection($collection2);
        $collection->addCollection($collection1);

        $this->assertEquals('/foo2', $collection1->get('foo')->getPath());
        $this->assertEquals('/foo2', $collection->get('foo')->getPath());
    }

    public function testIterator()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection1->add('bar', $bar = new Route('/bar'));
        $collection1->add('foo', $foo = new Route('/foo-new'));
        $collection->addCollection($collection1);
        $collection->add('last', $last = new Route('/last'));

        $this->assertInstanceOf(\ArrayIterator::class, $collection->getIterator());
        $this->assertSame(['bar' => $bar, 'foo' => $foo, 'last' => $last], $collection->getIterator()->getArrayCopy());
    }

    public function testCount()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection1->add('bar', new Route('/bar'));
        $collection->addCollection($collection1);

        $this->assertCount(2, $collection);
    }

    public function testAddCollection()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection1->add('bar', $bar = new Route('/bar'));
        $collection1->add('foo', $foo = new Route('/foo-new'));

        $collection2 = new RouteCollection();
        $collection2->add('grandchild', $grandchild = new Route('/grandchild'));

        $collection1->addCollection($collection2);
        $collection->addCollection($collection1);
        $collection->add('last', $last = new Route('/last'));

        $this->assertSame(['bar' => $bar, 'foo' => $foo, 'grandchild' => $grandchild, 'last' => $last], $collection->all(),
            '->addCollection() imports routes of another collection, overrides if necessary and adds them at the end');
    }

    public function testAddCollectionWithResources()
    {
        $collection = new RouteCollection();
        $collection->addResource($foo = new FileResource(__DIR__.'/Fixtures/foo.xml'));
        $collection1 = new RouteCollection();
        $collection1->addResource($foo1 = new FileResource(__DIR__.'/Fixtures/foo1.xml'));
        $collection->addCollection($collection1);
        $this->assertEquals([$foo, $foo1], $collection->getResources(), '->addCollection() merges resources');
    }

    public function testAddDefaultsAndRequirementsAndOptions()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/{placeholder}'));
        $collection1 = new RouteCollection();
        $collection1->add('bar', new Route('/{placeholder}',
            ['_controller' => 'fixed', 'placeholder' => 'default'], ['placeholder' => '.+'], ['option' => 'value'])
        );
        $collection->addCollection($collection1);

        $collection->addDefaults(['placeholder' => 'new-default']);
        $this->assertEquals(['placeholder' => 'new-default'], $collection->get('foo')->getDefaults(), '->addDefaults() adds defaults to all routes');
        $this->assertEquals(['_controller' => 'fixed', 'placeholder' => 'new-default'], $collection->get('bar')->getDefaults(),
            '->addDefaults() adds defaults to all routes and overwrites existing ones');

        $collection->addRequirements(['placeholder' => '\d+']);
        $this->assertEquals(['placeholder' => '\d+'], $collection->get('foo')->getRequirements(), '->addRequirements() adds requirements to all routes');
        $this->assertEquals(['placeholder' => '\d+'], $collection->get('bar')->getRequirements(),
            '->addRequirements() adds requirements to all routes and overwrites existing ones');

        $collection->addOptions(['option' => 'new-value']);
        $this->assertEquals(
            ['option' => 'new-value', 'compiler_class' => 'Symfony\\Component\\Routing\\RouteCompiler'],
            $collection->get('bar')->getOptions(), '->addOptions() adds options to all routes and overwrites existing ones'
        );
    }

    public function testAddPrefix()
    {
        $collection = new RouteCollection();
        $collection->add('foo', $foo = new Route('/foo'));
        $collection2 = new RouteCollection();
        $collection2->add('bar', $bar = new Route('/bar'));
        $collection->addCollection($collection2);
        $collection->addPrefix(' / ');
        $this->assertSame('/foo', $collection->get('foo')->getPath(), '->addPrefix() trims the prefix and a single slash has no effect');
        $collection->addPrefix('/{admin}', ['admin' => 'admin'], ['admin' => '\d+']);
        $this->assertEquals('/{admin}/foo', $collection->get('foo')->getPath(), '->addPrefix() adds a prefix to all routes');
        $this->assertEquals('/{admin}/bar', $collection->get('bar')->getPath(), '->addPrefix() adds a prefix to all routes');
        $this->assertEquals(['admin' => 'admin'], $collection->get('foo')->getDefaults(), '->addPrefix() adds defaults to all routes');
        $this->assertEquals(['admin' => 'admin'], $collection->get('bar')->getDefaults(), '->addPrefix() adds defaults to all routes');
        $this->assertEquals(['admin' => '\d+'], $collection->get('foo')->getRequirements(), '->addPrefix() adds requirements to all routes');
        $this->assertEquals(['admin' => '\d+'], $collection->get('bar')->getRequirements(), '->addPrefix() adds requirements to all routes');
        $collection->addPrefix('0');
        $this->assertEquals('/0/{admin}/foo', $collection->get('foo')->getPath(), '->addPrefix() ensures a prefix must start with a slash and must not end with a slash');
        $collection->addPrefix('/ /');
        $this->assertSame('/ /0/{admin}/foo', $collection->get('foo')->getPath(), '->addPrefix() can handle spaces if desired');
        $this->assertSame('/ /0/{admin}/bar', $collection->get('bar')->getPath(), 'the route pattern of an added collection is in synch with the added prefix');
    }

    public function testAddPrefixOverridesDefaultsAndRequirements()
    {
        $collection = new RouteCollection();
        $collection->add('foo', $foo = new Route('/foo.{_format}'));
        $collection->add('bar', $bar = new Route('/bar.{_format}', [], ['_format' => 'json']));
        $collection->addPrefix('/admin', [], ['_format' => 'html']);

        $this->assertEquals('html', $collection->get('foo')->getRequirement('_format'), '->addPrefix() overrides existing requirements');
        $this->assertEquals('html', $collection->get('bar')->getRequirement('_format'), '->addPrefix() overrides existing requirements');
    }

    public function testResource()
    {
        $collection = new RouteCollection();
        $collection->addResource($foo = new FileResource(__DIR__.'/Fixtures/foo.xml'));
        $collection->addResource($bar = new FileResource(__DIR__.'/Fixtures/bar.xml'));
        $collection->addResource(new FileResource(__DIR__.'/Fixtures/foo.xml'));

        $this->assertEquals([$foo, $bar], $collection->getResources(),
            '->addResource() adds a resource and getResources() only returns unique ones by comparing the string representation');
    }

    public function testUniqueRouteWithGivenName()
    {
        $collection1 = new RouteCollection();
        $collection1->add('foo', new Route('/old'));
        $collection2 = new RouteCollection();
        $collection3 = new RouteCollection();
        $collection3->add('foo', $new = new Route('/new'));

        $collection2->addCollection($collection3);
        $collection1->addCollection($collection2);

        $this->assertSame($new, $collection1->get('foo'), '->get() returns new route that overrode previous one');
        // size of 1 because collection1 contains /new but not /old anymore
        $this->assertCount(1, $collection1->getIterator(), '->addCollection() removes previous routes when adding new routes with the same name');
    }

    public function testGet()
    {
        $collection1 = new RouteCollection();
        $collection1->add('a', $a = new Route('/a'));
        $collection2 = new RouteCollection();
        $collection2->add('b', $b = new Route('/b'));
        $collection1->addCollection($collection2);
        $collection1->add('$péß^a|', $c = new Route('/special'));

        $this->assertSame($b, $collection1->get('b'), '->get() returns correct route in child collection');
        $this->assertSame($c, $collection1->get('$péß^a|'), '->get() can handle special characters');
        $this->assertNull($collection2->get('a'), '->get() does not return the route defined in parent collection');
        $this->assertNull($collection1->get('non-existent'), '->get() returns null when route does not exist');
        $this->assertNull($collection1->get(0), '->get() does not disclose internal child RouteCollection');
    }

    public function testRemove()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));

        $collection1 = new RouteCollection();
        $collection1->add('bar', $bar = new Route('/bar'));
        $collection->addCollection($collection1);
        $collection->add('last', $last = new Route('/last'));
        $collection->addAlias('alias_removed_when_removing_route_foo', 'foo');
        $collection->addAlias('alias_directly_removed', 'bar');

        $collection->remove('foo');
        $this->assertSame(['bar' => $bar, 'last' => $last], $collection->all(), '->remove() can remove a single route');
        $collection->remove('alias_directly_removed');
        $this->assertNull($collection->getAlias('alias_directly_removed'));
        $collection->remove(['bar', 'last']);
        $this->assertSame([], $collection->all(), '->remove() accepts an array and can remove multiple routes at once');
        $this->assertNull($collection->getAlias('alias_removed_when_removing_route_foo'));
    }

    public function testSetHost()
    {
        $collection = new RouteCollection();
        $routea = new Route('/a');
        $routeb = new Route('/b', [], [], [], '{locale}.example.net');
        $collection->add('a', $routea);
        $collection->add('b', $routeb);

        $collection->setHost('{locale}.example.com');

        $this->assertEquals('{locale}.example.com', $routea->getHost());
        $this->assertEquals('{locale}.example.com', $routeb->getHost());
    }

    public function testSetCondition()
    {
        $collection = new RouteCollection();
        $routea = new Route('/a');
        $routeb = new Route('/b', [], [], [], '{locale}.example.net', [], [], 'context.getMethod() == "GET"');
        $collection->add('a', $routea);
        $collection->add('b', $routeb);

        $collection->setCondition('context.getMethod() == "POST"');

        $this->assertEquals('context.getMethod() == "POST"', $routea->getCondition());
        $this->assertEquals('(context.getMethod() == "GET") and (context.getMethod() == "POST")', $routeb->getCondition());
    }

    public function testClone()
    {
        $collection = new RouteCollection();
        $collection->add('a', new Route('/a'));
        $collection->add('b', new Route('/b', ['placeholder' => 'default'], ['placeholder' => '.+']));

        $clonedCollection = clone $collection;

        $this->assertCount(2, $clonedCollection);
        $this->assertEquals($collection->get('a'), $clonedCollection->get('a'));
        $this->assertNotSame($collection->get('a'), $clonedCollection->get('a'));
        $this->assertEquals($collection->get('b'), $clonedCollection->get('b'));
        $this->assertNotSame($collection->get('b'), $clonedCollection->get('b'));
    }

    public function testSetSchemes()
    {
        $collection = new RouteCollection();
        $routea = new Route('/a', [], [], [], '', 'http');
        $routeb = new Route('/b');
        $collection->add('a', $routea);
        $collection->add('b', $routeb);

        $collection->setSchemes(['http', 'https']);

        $this->assertEquals(['http', 'https'], $routea->getSchemes());
        $this->assertEquals(['http', 'https'], $routeb->getSchemes());
    }

    public function testSetMethods()
    {
        $collection = new RouteCollection();
        $routea = new Route('/a', [], [], [], '', [], ['GET', 'POST']);
        $routeb = new Route('/b');
        $collection->add('a', $routea);
        $collection->add('b', $routeb);

        $collection->setMethods('PUT');

        $this->assertEquals(['PUT'], $routea->getMethods());
        $this->assertEquals(['PUT'], $routeb->getMethods());
    }

    public function testAddNamePrefix()
    {
        $collection = new RouteCollection();
        $collection->add('foo', $foo = new Route('/foo'));
        $collection->add('bar', $bar = new Route('/bar'));
        $collection->add('api_foo', $apiFoo = new Route('/api/foo'));
        $collection->addNamePrefix('api_');

        $this->assertEquals($foo, $collection->get('api_foo'));
        $this->assertEquals($bar, $collection->get('api_bar'));
        $this->assertEquals($apiFoo, $collection->get('api_api_foo'));
        $this->assertNull($collection->get('foo'));
        $this->assertNull($collection->get('bar'));
    }

    public function testAddNamePrefixCanonicalRouteName()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo', ['_canonical_route' => 'foo']));
        $collection->add('bar', new Route('/bar', ['_canonical_route' => 'bar']));
        $collection->add('api_foo', new Route('/api/foo', ['_canonical_route' => 'api_foo']));
        $collection->addNamePrefix('api_');

        $this->assertEquals('api_foo', $collection->get('api_foo')->getDefault('_canonical_route'));
        $this->assertEquals('api_bar', $collection->get('api_bar')->getDefault('_canonical_route'));
        $this->assertEquals('api_api_foo', $collection->get('api_api_foo')->getDefault('_canonical_route'));
    }

    public function testAddWithPriority()
    {
        $collection = new RouteCollection();
        $collection->add('foo', $foo = new Route('/foo'), 0);
        $collection->add('bar', $bar = new Route('/bar'), 1);
        $collection->add('baz', $baz = new Route('/baz'));

        $expected = [
            'bar' => $bar,
            'foo' => $foo,
            'baz' => $baz,
        ];

        $this->assertSame($expected, $collection->all());

        $collection2 = new RouteCollection();
        $collection2->add('foo2', $foo2 = new Route('/foo'), 0);
        $collection2->add('bar2', $bar2 = new Route('/bar'), 1);
        $collection2->add('baz2', $baz2 = new Route('/baz'));
        $collection2->addCollection($collection);

        $expected = [
            'bar2' => $bar2,
            'bar' => $bar,
            'foo2' => $foo2,
            'baz2' => $baz2,
            'foo' => $foo,
            'baz' => $baz,
        ];

        $this->assertSame($expected, $collection2->all());
    }

    public function testAddWithPriorityAndPrefix()
    {
        $collection3 = new RouteCollection();
        $collection3->add('foo3', $foo3 = new Route('/foo'), 0);
        $collection3->add('bar3', $bar3 = new Route('/bar'), 1);
        $collection3->add('baz3', $baz3 = new Route('/baz'));
        $collection3->addNamePrefix('prefix_');

        $expected = [
            'prefix_bar3' => $bar3,
            'prefix_foo3' => $foo3,
            'prefix_baz3' => $baz3,
        ];

        $this->assertSame($expected, $collection3->all());
    }
}
