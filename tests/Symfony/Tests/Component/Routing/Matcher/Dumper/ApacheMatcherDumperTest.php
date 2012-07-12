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

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Matcher\Dumper\ApacheMatcherDumper;

class ApacheMatcherDumperTest extends \PHPUnit_Framework_TestCase
{
    protected static $fixturesPath;

    public static function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../../Fixtures/');
    }

    public function testDump()
    {
        $dumper = new ApacheMatcherDumper($this->getRouteCollection());

        $this->assertStringEqualsFile(self::$fixturesPath.'/dumper/url_matcher1.apache', $dumper->dump(), '->dump() dumps basic routes to the correct apache format.');
    }

    /**
     * @dataProvider provideEscapeFixtures
     */
    public function testEscapePattern($src, $dest, $char, $with, $message)
    {
        $r = new \ReflectionMethod(new ApacheMatcherDumper($this->getRouteCollection()), 'escape');
        $r->setAccessible(true);
        $this->assertEquals($dest, $r->invoke(null, $src, $char, $with), $message);
    }

    public function provideEscapeFixtures()
    {
        return array(
            array('foo', 'foo', ' ', '-', 'Preserve string that should not be escaped'),
            array('fo-o', 'fo-o', ' ', '-', 'Preserve string that should not be escaped'),
            array('fo o', 'fo- o', ' ', '-', 'Escape special characters'),
            array('fo-- o', 'fo--- o', ' ', '-', 'Escape special characters'),
            array('fo- o', 'fo- o', ' ', '-', 'Do not escape already escaped string'),
        );
    }

    public function testEscapeScriptName()
    {
        $collection = new RouteCollection();
        $collection->add('foo', new Route('/foo'));
        $dumper = new ApacheMatcherDumper($collection);
        $this->assertStringEqualsFile(self::$fixturesPath.'/dumper/url_matcher2.apache', $dumper->dump(array('script_name' => 'ap p_d\ ev.php')));
    }

    private function getRouteCollection()
    {
        $collection = new RouteCollection();

        // defaults and requirements
        $collection->add('foo', new Route(
            '/foo/{bar}',
            array('def' => 'test'),
            array('bar' => 'baz|symfony')
        ));
        // defaults parameters in pattern
        $collection->add('foobar', new Route(
            '/foo/{bar}',
            array('bar' => 'toto')
        ));
        // method requirement
        $collection->add('bar', new Route(
            '/bar/{foo}',
            array(),
            array('_method' => 'GET|head')
        ));
        // method requirement (again)
        $collection->add('baragain', new Route(
            '/baragain/{foo}',
            array(),
            array('_method' => 'get|post')
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
        // complex
        $collection->add('baz6', new Route(
            '/test/baz',
            array('foo' => 'bar baz')
        ));
        // space in path
        $collection->add('baz7', new Route(
            '/te st/baz'
        ));

        return $collection;
    }
}
