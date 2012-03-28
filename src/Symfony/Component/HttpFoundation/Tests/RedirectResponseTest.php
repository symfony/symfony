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

    public function testCreate()
    {
        $response = RedirectResponse::create('foo', 301);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertEquals(301, $response->getStatusCode());
    }
}
