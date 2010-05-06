<?php

/*
 * This file is part of the symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Components\HttpKernel;

use Symfony\Components\HttpKernel\Response;

class ResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testIsValidateable()
    {
        $response = new Response('', 200, array('Last-Modified' => $this->createDateTimeOneHourAgo()->format(DATE_RFC2822)));
        $this->assertTrue($response->isValidateable(), '->isValidateable() returns true if Last-Modified is present');

        $response = new Response('', 200, array('ETag' => '"12345"'));
        $this->assertTrue($response->isValidateable(), '->isValidateable() returns true if ETag is present');

        $response = new Response();
        $this->assertFalse($response->isValidateable(), '->isValidateable() returns false when no validator is present');
    }

    public function testGetDate()
    {
        $response = new Response('', 200, array('Date' => $this->createDateTimeOneHourAgo()->format(DATE_RFC2822)));
        $this->assertEquals(0, $this->createDateTimeOneHourAgo()->diff($response->getDate())->format('%s'), '->getDate() returns the Date header if present');

        $response = new Response();
        $date = $response->getDate();
        $this->assertLessThan(1, $date->diff(new \DateTime(), true)->format('%s'), '->getDate() returns the current Date if no Date header present');

        $response = new Response('', 200, array('Date' => $this->createDateTimeOneHourAgo()->format(DATE_RFC2822)));
        $now = $this->createDateTimeNow();
        $response->headers->set('Date', $now->format(DATE_RFC2822));
        $this->assertEquals(0, $now->diff($response->getDate())->format('%s'), '->getDate() returns the date when the header has been modified');
    }

    public function testGetMaxAge()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 's-maxage=600, max-age=0');
        $this->assertEquals(600, $response->getMaxAge(), '->getMaxAge() uses s-maxage cache control directive when present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=600');
        $this->assertEquals(600, $response->getMaxAge(), '->getMaxAge() falls back to max-age when no s-maxage directive present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'must-revalidate');
        $response->headers->set('Expires', $this->createDateTimeOneHourLater()->format(DATE_RFC2822));
        $this->assertEquals(3600, $response->getMaxAge(), '->getMaxAge() falls back to Expires when no max-age or s-maxage directive present');

        $response = new Response();
        $this->assertNull($response->getMaxAge(), '->getMaxAge() returns null if no freshness information available');
    }

    public function testIsPrivate()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=100');
        $response->setPrivate(true);
        $this->assertEquals(100, $response->headers->getCacheControl()->getMaxAge(), '->isPrivate() adds the private Cache-Control directive when set to true');
        $this->assertTrue($response->headers->getCacheControl()->isPrivate(), '->isPrivate() adds the private Cache-Control directive when set to true');

        $response = new Response();
        $response->headers->set('Cache-Control', 'public, max-age=100');
        $response->setPrivate(true);
        $this->assertEquals(100, $response->headers->getCacheControl()->getMaxAge(), '->isPrivate() adds the private Cache-Control directive when set to true');
        $this->assertTrue($response->headers->getCacheControl()->isPrivate(), '->isPrivate() adds the private Cache-Control directive when set to true');
        $this->assertFalse($response->headers->getCacheControl()->isPublic(), '->isPrivate() removes the public Cache-Control directive');
    }

    public function testExpire()
    {
        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=100');
        $response->expire();
        $this->assertEquals(100, $response->headers->get('Age'), '->expire() sets the Age to max-age when present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=100, s-maxage=500');
        $response->expire();
        $this->assertEquals(500, $response->headers->get('Age'), '->expire() sets the Age to s-maxage when both max-age and s-maxage are present');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=5, s-maxage=500');
        $response->headers->set('Age', '1000');
        $response->expire();
        $this->assertEquals(1000, $response->headers->get('Age'), '->expire() does nothing when the response is already stale/expired');

        $response = new Response();
        $response->expire();
        $this->assertFalse($response->headers->has('Age'), '->expire() does nothing when the response does not include freshness information');
    }

    public function testGetTtl()
    {
        $response = new Response();
        $this->assertNull($response->getTtl(), '->getTtl() returns null when no Expires or Cache-Control headers are present');

        $response = new Response();
        $response->headers->set('Expires', $this->createDateTimeOneHourLater()->format(DATE_RFC2822));
        $this->assertLessThan(1, 3600 - $response->getTtl(), '->getTtl() uses the Expires header when no max-age is present');

        $response = new Response();
        $response->headers->set('Expires', $this->createDateTimeOneHourAgo()->format(DATE_RFC2822));
        $this->assertLessThan(0, $response->getTtl(), '->getTtl() returns negative values when Expires is in part');

        $response = new Response();
        $response->headers->set('Cache-Control', 'max-age=60');
        $this->assertLessThan(1, 60 - $response->getTtl(), '->getTtl() uses Cache-Control max-age when present');
    }

    public function testGetVary()
    {
        $response = new Response();
        $this->assertEquals(array(), $response->getVary(), '->getVary() returns an empty array if no Vary header is present');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language');
        $this->assertEquals(array('Accept-Language'), $response->getVary(), '->getVary() parses a single header name value');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language User-Agent    X-Foo');
        $this->assertEquals(array('Accept-Language', 'User-Agent', 'X-Foo'), $response->getVary(), '->getVary() parses multiple header name values separated by spaces');

        $response = new Response();
        $response->headers->set('Vary', 'Accept-Language,User-Agent,    X-Foo');
        $this->assertEquals(array('Accept-Language', 'User-Agent', 'X-Foo'), $response->getVary(), '->getVary() parses multiple header name values separated by commas');
    }

    protected function createDateTimeOneHourAgo()
    {
        $date = new \DateTime();

        return $date->sub(new \DateInterval('PT1H'));
    }

    protected function createDateTimeOneHourLater()
    {
        $date = new \DateTime();

        return $date->add(new \DateInterval('PT1H'));
    }

    protected function createDateTimeNow()
    {
        return new \DateTime();
    }
}
