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
use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper;

class PhpMatcherDumperTest extends \PHPUnit_Framework_TestCase
{
    static protected $fixturesPath;

    static public function setUpBeforeClass()
    {
        self::$fixturesPath = realpath(__DIR__.'/../../Fixtures/');
    }

    public function testDump()
    {
        $collection = new RouteCollection();

        $collection->addRoute('foo', new Route(
            '/foo/:bar',
            array('def' => 'test'),
            array('bar' => 'baz|symfony')
        ));
        $collection->addRoute('bar', new Route(
            '/bar/:foo',
            array(),
            array('_method' => array('GET', 'HEAD'))
        ));
        $dumper = new PhpMatcherDumper($collection);
        $this->assertStringEqualsFile(self::$fixturesPath.'/dumper/url_matcher1.php', $dumper->dump(), '->dump() dumps basic routes to the correct PHP file.');
    }
}
