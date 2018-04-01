<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Tests\EntryPoint;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Security\Http\EntryPoint\RetryAuthenticationEntryPoint;
use Symphony\Component\HttpFoundation\Request;

class RetryAuthenticationEntryPointTest extends TestCase
{
    /**
     * @dataProvider dataForStart
     */
    public function testStart($httpPort, $httpsPort, $request, $expectedUrl)
    {
        $entryPoint = new RetryAuthenticationEntryPoint($httpPort, $httpsPort);
        $response = $entryPoint->start($request);

        $this->assertInstanceOf('Symphony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertEquals($expectedUrl, $response->headers->get('Location'));
    }

    public function dataForStart()
    {
        if (!class_exists('Symphony\Component\HttpFoundation\Request')) {
            return array(array());
        }

        return array(
            array(
                80,
                443,
                Request::create('http://localhost/foo/bar?baz=bat'),
                'https://localhost/foo/bar?baz=bat',
            ),
            array(
                80,
                443,
                Request::create('https://localhost/foo/bar?baz=bat'),
                'http://localhost/foo/bar?baz=bat',
            ),
            array(
                80,
                123,
                Request::create('http://localhost/foo/bar?baz=bat'),
                'https://localhost:123/foo/bar?baz=bat',
            ),
            array(
                8080,
                443,
                Request::create('https://localhost/foo/bar?baz=bat'),
                'http://localhost:8080/foo/bar?baz=bat',
            ),
        );
    }
}
