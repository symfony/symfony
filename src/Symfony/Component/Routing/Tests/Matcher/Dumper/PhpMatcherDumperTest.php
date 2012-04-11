<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Matcher\Dumper;

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;

class PhpMatcherDumperTest extends \PHPUnit_Framework_TestCase
{
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

    /**
     * @dataProvider getRouteCollections
     */
    public function testDump(RouteCollection $collection, $fixture, $options = array())
    {
        $basePath = __DIR__.'/../../Fixtures/dumper/';

        $dumper = new PhpMatcherDumper($collection, new RequestContext());

        $this->assertStringEqualsFile($basePath.$fixture, $dumper->dump($options), '->dump() correctly dumps routes as optimized PHP code.');
    }

    public function getRouteCollections()
    {
        /* test case 1 */

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
        // space in pattern
        $collection->add('space', new Route(
            '/spa ce'
        ));

        // prefixes
        $collection1 = new RouteCollection();
        $collection1->add('overriden', new Route('/overriden1'));
        $collection1->add('foo1', new Route('/{foo}'));
        $collection1->add('bar1', new Route('/{bar}'));
        $collection2 = new RouteCollection();
        $collection2->addCollection($collection1, '/b\'b');
        $collection2->add('overriden', new Route('/{var}', array(), array('var' => '.*')));
        $collection1 = new RouteCollection();
        $collection1->add('foo2', new Route('/{foo1}'));
        $collection1->add('bar2', new Route('/{bar1}'));
        $collection2->addCollection($collection1, '/b\'b');
        $collection->addCollection($collection2, '/a');

        // overriden through addCollection() and multiple sub-collections with no own prefix
        $collection1 = new RouteCollection();
        $collection1->add('overriden2', new Route('/old'));
        $collection1->add('helloWorld', new Route('/hello/{who}', array('who' => 'World!')));
        $collection2 = new RouteCollection();
        $collection3 = new RouteCollection();
        $collection3->add('overriden2', new Route('/new'));
        $collection3->add('hey', new Route('/hey/'));
        $collection1->addCollection($collection2);
        $collection2->addCollection($collection3);
        $collection->addCollection($collection1, '/multi');

        // "dynamic" prefix
        $collection1 = new RouteCollection();
        $collection1->add('foo3', new Route('/{foo}'));
        $collection1->add('bar3', new Route('/{bar}'));
        $collection2 = new RouteCollection();
        $collection2->addCollection($collection1, '/b');
        $collection->addCollection($collection2, '/{_locale}');

        // route between collections
        $collection->add('ababa', new Route('/ababa'));

        // collection with static prefix but only one route
        $collection1 = new RouteCollection();
        $collection1->add('foo4', new Route('/{foo}'));
        $collection->addCollection($collection1, '/aba');

        // multiple sub-collections with a single route and a prefix each
        $collection1 = new RouteCollection();
        $collection1->add('a', new Route('/a...'));
        $collection2 = new RouteCollection();
        $collection2->add('b', new Route('/{var}'));
        $collection3 = new RouteCollection();
        $collection3->add('c', new Route('/{var}'));

        $collection1->addCollection($collection2, '/b');
        $collection2->addCollection($collection3, '/c');
        $collection->addCollection($collection1, '/a');

        /* test case 2 */

        $redirectCollection = clone $collection;

        // force HTTPS redirection
        $redirectCollection->add('secure', new Route(
            '/secure',
            array(),
            array('_scheme' => 'https')
        ));

        // force HTTP redirection
        $redirectCollection->add('nonsecure', new Route(
            '/nonsecure',
            array(),
            array('_scheme' => 'http')
        ));

        /* test case 3 */

        $rootprefixCollection = new RouteCollection();
        $rootprefixCollection->add('static', new Route('/test'));
        $rootprefixCollection->add('dynamic', new Route('/{var}'));
        $rootprefixCollection->addPrefix('rootprefix');

        return array(
           array($collection, 'url_matcher1.php', array()),
           array($redirectCollection, 'url_matcher2.php', array('base_class' => 'Symfony\Component\Routing\Tests\Fixtures\RedirectableUrlMatcher')),
           array($rootprefixCollection, 'url_matcher3.php', array())
        );
    }
}
