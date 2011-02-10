<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Matcher\Dumper\ApacheMatcherDumper;

class ApacheMatcherDumperTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../../Fixtures/');
    }

    public function testDump()
    {
        $collection = new RouteCollection();

        $collection->add('foo', new Route(
            '/foo/{bar}',
            array('def' => 'test'),
            array('bar' => 'baz|symfony')
        ));
        $collection->add('bar', new Route(
            '/bar/{foo}',
            array(),
            array('_method' => 'GET|head')
        ));
        $collection->add('baz', new Route(
            '/test/baz/'
        ));
        $collection->add('baz2', new Route(
            '/test/{foo}/'
        ));

        $dumper = new ApacheMatcherDumper($collection);

        $this->assertStringEqualsFile(self::$fixturesPath.'/dumper/url_matcher1.apache', $dumper->dump(), '->dump() dumps basic routes to the correct apache format.');
    }
}
