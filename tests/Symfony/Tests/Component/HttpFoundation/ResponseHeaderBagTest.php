<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ResponseHeaderBagTest extends \PHPUnit_Framework_TestCase
{
    public function testCacheControlHeader()
    {
        $bag = new ResponseHeaderBag(array(), 'response');
        $this->assertEquals('no-cache', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('no-cache'));

        $bag = new ResponseHeaderBag(array('Cache-Control' => 'public'), 'response');
        $this->assertEquals('public', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('public'));

        $bag = new ResponseHeaderBag(array('ETag' => 'abcde'), 'response');
        $this->assertEquals('private, max-age=0, must-revalidate', $bag->get('Cache-Control'));
        $this->assertTrue($bag->hasCacheControlDirective('private'));
        $this->assertTrue($bag->hasCacheControlDirective('must-revalidate'));
        $this->assertEquals(0, $bag->getCacheControlDirective('max-age'));

        $bag = new ResponseHeaderBag(array('Last-Modified' => 'abcde'), 'response');
        $this->assertEquals('private, max-age=0, must-revalidate', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('Etag' => 'abcde', 'Last-Modified' => 'abcde'), 'response');
        $this->assertEquals('private, max-age=0, must-revalidate', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('cache-control' => 'max-age=100'), 'response');
        $this->assertEquals('max-age=100, private', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('cache-control' => 's-maxage=100'), 'response');
        $this->assertEquals('s-maxage=100', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('cache-control' => 'private, max-age=100'), 'response');
        $this->assertEquals('max-age=100, private', $bag->get('Cache-Control'));

        $bag = new ResponseHeaderBag(array('cache-control' => 'public, max-age=100'), 'response');
        $this->assertEquals('max-age=100, public', $bag->get('Cache-Control'));
    }
}
