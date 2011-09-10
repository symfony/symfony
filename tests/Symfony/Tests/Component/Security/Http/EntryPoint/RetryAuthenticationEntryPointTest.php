<?php

namespace Symfony\Tests\Component\Security\Http\EntryPoint;

use Symfony\Component\Security\Http\EntryPoint\RetryAuthenticationEntryPoint;
use Symfony\Component\HttpFoundation\Request;

class RetryAuthenticationEntryPointTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataForStart
     */
    public function testStart($httpPort, $httpsPort, $request, $expectedUrl)
    {
        $entryPoint = new RetryAuthenticationEntryPoint($httpPort, $httpsPort);
        $response = $entryPoint->start($request);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertEquals($expectedUrl, $response->headers->get('Location'));
    }

    public function dataForStart()
    {
        return array(
            array(
                80,
                443,
                Request::create('http://localhost/foo/bar?baz=bat'),
                'https://localhost/foo/bar?baz=bat'
            ),
            array(
                80,
                443,
                Request::create('https://localhost/foo/bar?baz=bat'),
                'http://localhost/foo/bar?baz=bat'
            ),
            array(
                80,
                123,
                Request::create('http://localhost/foo/bar?baz=bat'),
                'https://localhost:123/foo/bar?baz=bat'
            ),
            array(
                8080,
                443,
                Request::create('https://localhost/foo/bar?baz=bat'),
                'http://localhost:8080/foo/bar?baz=bat'
            )
        );
    }
}
