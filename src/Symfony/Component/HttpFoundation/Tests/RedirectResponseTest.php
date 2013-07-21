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

        $this->assertEquals(1, preg_match(
            '#<meta http-equiv="refresh" content="\d+;url=foo\.bar" />#',
            preg_replace(array('/\s+/', '/\'/'), array(' ', '"'), $response->getContent())
        ));
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

    public function testRefreshTimeoutCanBeSetViaConstructor()
    {
        $response = new RedirectResponse('/foo', 301, array(), 3);

        $this->assertEquals(3, $response->getRefreshTimeout());
        $this->assertContains('<meta http-equiv="refresh" content="3;url=/foo" />', $response->getContent());
    }

    public function testRefreshTimeoutCanBeSetViaSetter()
    {
        $response = new RedirectResponse('foo');
        $response->setRefreshTimeout(5);

        $this->assertEquals(5, $response->getRefreshTimeout());
    }

    /**
     * @dataProvider provideRefreshTimeoutValues
     */
    public function testRefreshTimeoutShouldBeAPositiveInteger($given, $expected)
    {
        $response = new RedirectResponse('foo');
        $response->setRefreshTimeout($given);

        $this->assertEquals($expected, $response->getRefreshTimeout());
    }

    public function provideRefreshTimeoutValues()
    {
        return array(
            array(-1, 1),
            array(0, 1),
            array(5, 5),
            array(null, 1),
            array('baz', 1),
            array('77', 77)
        );
    }
}
