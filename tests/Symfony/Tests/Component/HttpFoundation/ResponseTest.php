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

use Symfony\Component\HttpFoundation\Response;

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
        $response->setPrivate();
        $this->assertEquals(100, $response->headers->getCacheControlDirective('max-age'), '->isPrivate() adds the private Cache-Control directive when set to true');
        $this->assertTrue($response->headers->getCacheControlDirective('private'), '->isPrivate() adds the private Cache-Control directive when set to true');

        $response = new Response();
        $response->headers->set('Cache-Control', 'public, max-age=100');
        $response->setPrivate();
        $this->assertEquals(100, $response->headers->getCacheControlDirective('max-age'), '->isPrivate() adds the private Cache-Control directive when set to true');
        $this->assertTrue($response->headers->getCacheControlDirective('private'), '->isPrivate() adds the private Cache-Control directive when set to true');
        $this->assertFalse($response->headers->hasCacheControlDirective('public'), '->isPrivate() removes the public Cache-Control directive');
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

    public function testDefaultContentType()
    {
        $headerMock = $this->getMock('Symfony\Component\HttpFoundation\ResponseHeaderBag', array('set'));
        $headerMock->expects($this->at(0))
            ->method('set')
            ->with('Content-Type', 'text/html; charset=UTF-8');
        $headerMock->expects($this->at(1))
            ->method('set')
            ->with('Content-Type', 'text/html; charset=Foo');

        $response = new Response();
        $response->headers = $headerMock;

        // verify first set()
        $response->__toString();

        $response->headers->remove('Content-Type');
        $response->setCharset('Foo');
        // verify second set()
        $response->__toString();
    }
    
    public function testSendContent()
    {
        $response = new Response();
        $response->setContent('foo');
        
        ob_start();
        $response->sendContent();
        $output = ob_get_clean();
        
        $this->assertSame('foo',$output);
    }
    
    public function testPublicPrivate()
    {
        $response = new Response();
        
        $response->setPrivate();
        
        $this->assertTrue($response->headers->getCacheControlDirective('private'));
        
        $response->setPublic();
        
        $this->assertTrue($response->headers->getCacheControlDirective('public'));
    }
    
    public function testExpires()
    {
        $date = new \DateTime('@1111');
        $response = new Response();
        $response->setExpires($date);
        
        $this->assertInstanceOf('\DateTime',$response->getExpires());
        
        $response->setExpires();
        
        $this->assertFalse($response->headers->hasCacheControlDirective('Expires'));
    }
    
    public function testClientTtl()
    {
        $response = new Response();
        
        $response->setClientTtl(1337);
        
        $this->assertEquals(1337,$response->getMaxAge());
    }
    
    public function testSetCache()
    {
        $response = new Response();
        
        $options = array('etag'=>'foo', 'last_modified'=>new \DateTime('@1337'), 'max_age'=>1337, 's_maxage'=>null, 'private'=>false, 'public'=>true);
        $response->setCache($options);
        
        $this->assertInstanceOf('\DateTime',$response->getLastModified());
        $this->assertEquals(1337,$response->getMaxAge());
        $this->assertTrue($response->headers->getCacheControlDirective('public'));
        $this->assertNull($response->headers->getCacheControlDirective('private'));
        $this->assertSame('"foo"',$response->getEtag());
        
        $options = array('etag'=>'foo', 'last_modified'=>new \DateTime('@1337'), 'max_age'=>null, 's_maxage'=>42, 'private'=>true, 'public'=>false);
        $response->setCache($options);
        
        $this->assertEquals(42,$response->getMaxAge());
        $this->assertNull($response->headers->getCacheControlDirective('public'));
        $this->assertTrue($response->headers->getCacheControlDirective('private'));
    }
    
    /**
     * @expectedException InvalidArgumentException
     */
    public function testSetInvalidCache()
    {
        $response = new Response();
        $response->setCache(array('foo'));
    }
    
    /**
     * @dataProvider statusCodeProvider
     */
    public function testStatusCodes($code)
    {
        $response = new Response();
        $response->setStatusCode($code);
        
        switch($code){
            case 100:
                $this->assertTrue($response->isInformational());
                break;
            case 400:
                $this->assertTrue($response->isClientError());
                break;
            case 500:
                $this->assertTrue($response->isServerError());
                break;
            case 200:
                $this->assertTrue($response->isOk());
                $this->assertTrue($response->isSuccessful());
                break;
            case 403:
                $this->assertTrue($response->isForbidden());
                break;
            case 404:
                $this->assertTrue($response->isNotFound());
                break;
            case 301:
            case 302:
            case 303:
            case 307:
                $this->assertTrue($response->isRedirection());
                $this->assertTrue($response->isRedirect());
                break;
            case 201:
            case 204:
            case 304:
                $this->assertTrue($response->isEmpty());
                break;
        }
    }
    
    public function statusCodeProvider()
    {
        return array(
            array(500),
            array(100),
            array(400),
            array(200),
            array(403),
            array(404),
            array(301),
            array(302),
            array(303),
            array(307),
            array(201),
            array(204),
            array(304)
        );
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
