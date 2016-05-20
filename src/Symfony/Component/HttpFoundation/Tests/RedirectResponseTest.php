<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use \Symfony\Component\HttpFoundation\RedirectResponse;

class RedirectResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testGenerateMetaRedirect()
    {
        $response = new RedirectResponse('foo.bar');
        $this->assertMetaRefreshUrl('foo.bar', $response->getContent());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRedirectResponseConstructorNullUrl()
    {
        $response = new RedirectResponse(null);
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testRedirectResponseConstructorWrongStatusCode()
    {
        $response = new RedirectResponse('foo.bar', 404);
    }

    public function testGenerateLocationHeader()
    {
        $response = new RedirectResponse('foo.bar');

        $this->assertTrue($response->headers->has('Location'));
        $this->assertEquals('foo.bar', $response->headers->get('Location'));
    }

    public function testGetTargetUrl()
    {
        $response = new RedirectResponse('foo.bar');

        $this->assertEquals('foo.bar', $response->getTargetUrl());
    }

    public function testSetTargetUrl()
    {
        $response = new RedirectResponse('foo.bar');
        $response->setTargetUrl('baz.beep');

        $this->assertEquals('baz.beep', $response->getTargetUrl());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testSetTargetUrlNull()
    {
        $response = new RedirectResponse('foo.bar');
        $response->setTargetUrl(null);
    }

    public function testCreate()
    {
        $response = RedirectResponse::create('foo', 301);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    /** @dataProvider provideUrlencodedUrls */
    public function testVariousEncodingUrls($urlencodedUrl)
    {
        # This is necessary due to http://www.php.net/manual/en/function.htmlspecialchars.php#102871:
        ini_set('display_errors', false);

        // $urlencodedUrl is what is sent by the browser on the HTTP level
        $decoded = urldecode($urlencodedUrl); // This is what we get in PHP (webserver does the decoding)
        $test = "/test-";

        $response = RedirectResponse::create($test.$decoded);

        $this->assertEquals($test.$urlencodedUrl, $response->headers->get('Location'));
        $this->assertMetaRefreshUrl($test.$decoded, $response->getContent());
    }

    public function provideUrlencodedUrls() {
        return array(
            array("%C3%A4"), // german umlaut, utf-8 and url-encoded
            array("%E4"), // german umlaut, latin-1 and url-encoded
        );
    }

    protected function assertMetaRefreshUrl($url, $content)
    {
        $url = preg_quote($url, '#');

        $this->assertEquals(1, preg_match(
            '#<meta http-equiv="refresh" content="\d+;url='.$url.'" />#',
            preg_replace(array('/\s+/', '/\'/'), array(' ', '"'), $content)
        ));
    }
}
