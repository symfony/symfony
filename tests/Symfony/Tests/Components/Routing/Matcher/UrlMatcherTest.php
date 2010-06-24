<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\Routing\Matcher;

use Symfony\Components\Routing\Matcher\UrlMatcher;
use Symfony\Components\Routing\RouteCollection;
use Symfony\Components\Routing\Route;

class UrlMatcherTest extends \PHPUnit_Framework_TestCase
{
    public function testNormalizeUrl()
    {
        $collection = new RouteCollection();
        $collection->addRoute('foo', new Route('/:foo'));

        $matcher = new UrlMatcherForTests($collection, array(), array());

        $this->assertEquals('/', $matcher->normalizeUrl(''), '->normalizeUrl() adds a / at the beginning of the URL if needed');
        $this->assertEquals('/foo', $matcher->normalizeUrl('foo'), '->normalizeUrl() adds a / at the beginning of the URL if needed');
        $this->assertEquals('/foo', $matcher->normalizeUrl('/foo?foo=bar'), '->normalizeUrl() removes the query string');
        $this->assertEquals('/foo/bar', $matcher->normalizeUrl('/foo//bar'), '->normalizeUrl() removes duplicated /');
    }
}

class UrlMatcherForTests extends UrlMatcher
{
    public function normalizeUrl($url)
    {
        return parent::normalizeUrl($url);
    }
}
