<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing;

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;

class PhpMatcherDumperTest extends \PHPUnit_Framework_TestCase
{
    public function testDump()
    {
        $dumper = new PhpMatcherDumper($this->getRouteCollection(), new RequestContext());

        $this->assertStringEqualsFile(__DIR__.'/../../Fixtures/dumper/url_matcher1.php', $dumper->dump(), '->dump() dumps basic routes to the correct PHP file.');

        $collection = $this->getRouteCollection();

        // force HTTPS redirection
        $collection->add('secure', new Route(
            '/secure',
            array(),
            array('_scheme' => 'https')
        ));

        // force HTTP redirection
        $collection->add('nonsecure', new Route(
            '/nonsecure',
            array(),
            array('_scheme' => 'http')
        ));

        $dumper = new PhpMatcherDumper($collection, new RequestContext());

        $this->assertStringEqualsFile(__DIR__.'/../../Fixtures/dumper/url_matcher2.php', $dumper->dump(array('base_class' => 'Symfony\Tests\Component\Routing\Fixtures\RedirectableUrlMatcher')), '->dump() dumps basic routes to the correct PHP file.');
    }

    /**
     * @expectedException \LogicException
     */
    public function testDumpWhenSchemeIsUsedWithoutAProperDumper()
    {
        $collection = new RouteCollection();
        $collection->add('secure', new Route(
            '/secure',
            array(),
            array('_scheme' => 'https')
        ));
        $dumper = new PhpMatcherDumper($collection, new RequestContext());
        $dumper->dump();
    }

    protected function getRouteCollection()
    {
        $collection = new RouteCollection();

        $collection->add('overriden', new Route('/overriden'));

        // defaults and requirements
        $collection->add('foo', new Route(
            '/foo/{bar}',
            array('def' => 'test'),
            array('bar' => 'baz|symfony')
        ));
        // method requirement
        $collection->add('bar', new Route(
            '/bar/{foo}',
            array(),
            array('_method' => 'GET|head')
        ));
        // GET method requirement automatically adds HEAD as valid
        $collection->add('barhead', new Route(
            '/barhead/{foo}',
            array(),
            array('_method' => 'GET')
        ));
        // simple
        $collection->add('baz', new Route(
            '/test/baz'
        ));
        // simple with extension
        $collection->add('baz2', new Route(
            '/test/baz.html'
        ));
        // trailing slash
        $collection->add('baz3', new Route(
            '/test/baz3/'
        ));
        // trailing slash with variable
        $collection->add('baz4', new Route(
            '/test/{foo}/'
        ));
        // trailing slash and method
        $collection->add('baz5', new Route(
            '/test/{foo}/',
            array(),
            array('_method' => 'post')
        ));
        // complex name
        $collection->add('baz.baz6', new Route(
            '/test/{foo}/',
            array(),
            array('_method' => 'put')
        ));
        // defaults without variable
        $collection->add('foofoo', new Route(
            '/foofoo',
            array('def' => 'test')
        ));
        // pattern with quotes
        $collection->add('quoter', new Route(
            '/{quoter}',
            array(),
            array('quoter' => '[\']+')
        ));

        // prefixes
        $collection1 = new RouteCollection();
        $collection1->add('overriden', new Route('/overriden1'));
        $collection1->add('foo1', new Route('/{foo}'));
        $collection1->add('bar1', new Route('/{bar}'));
        $collection2 = new RouteCollection();
        $collection2->addCollection($collection1, '/b\'b');
        $collection2->add('overriden', new Route('/overriden2'));
        $collection1 = new RouteCollection();
        $collection1->add('foo2', new Route('/{foo1}'));
        $collection1->add('bar2', new Route('/{bar1}'));
        $collection2->addCollection($collection1, '/b\'b');
        $collection->addCollection($collection2, '/a');

        // "dynamic" prefix
        $collection1 = new RouteCollection();
        $collection1->add('foo3', new Route('/{foo}'));
        $collection1->add('bar3', new Route('/{bar}'));
        $collection2 = new RouteCollection();
        $collection2->addCollection($collection1, '/b');
        $collection->addCollection($collection2, '/{_locale}');

        $collection->add('ababa', new Route('/ababa'));

        // some more prefixes
        $collection1 = new RouteCollection();
        $collection1->add('foo4', new Route('/{foo}'));
        $collection->addCollection($collection1, '/aba');

        return $collection;
    }
}
