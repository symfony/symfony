<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Csp;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicyHandlerTest extends TestCase
{
    /**
     * @dataProvider provideRequestAndResponses
     */
    public function testGetNonces($nonce, $expectedNonce, Request $request, Response $response)
    {
        $cspHandler = new ContentSecurityPolicyHandler($this->mockNonceGenerator($nonce));

        $this->assertSame($expectedNonce, $cspHandler->getNonces($request, $response));
    }

    /**
     * @dataProvider provideRequestAndResponsesForOnKernelResponse
     */
    public function testOnKernelResponse($nonce, $expectedNonce, Request $request, Response $response, array $expectedCsp)
    {
        $cspHandler = new ContentSecurityPolicyHandler($this->mockNonceGenerator($nonce));

        $this->assertSame($expectedNonce, $cspHandler->updateResponseHeaders($request, $response));

        $this->assertFalse($response->headers->has('X-SymfonyProfiler-Script-Nonce'));
        $this->assertFalse($response->headers->has('X-SymfonyProfiler-Style-Nonce'));

        foreach ($expectedCsp as $header => $value) {
            $this->assertSame($value, $response->headers->get($header));
        }
    }

    public function provideRequestAndResponses()
    {
        $nonce = bin2hex(random_bytes(16));

        $requestScriptNonce = 'request-with-headers-script-nonce';
        $requestStyleNonce = 'request-with-headers-style-nonce';

        $responseScriptNonce = 'response-with-headers-script-nonce';
        $responseStyleNonce = 'response-with-headers-style-nonce';

        $requestNonceHeaders = array(
            'X-SymfonyProfiler-Script-Nonce' => $requestScriptNonce,
            'X-SymfonyProfiler-Style-Nonce' => $requestStyleNonce,
        );
        $responseNonceHeaders = array(
            'X-SymfonyProfiler-Script-Nonce' => $responseScriptNonce,
            'X-SymfonyProfiler-Style-Nonce' => $responseStyleNonce,
        );

        return array(
            array($nonce, array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce), $this->createRequest(), $this->createResponse()),
            array($nonce, array('csp_script_nonce' => $requestScriptNonce, 'csp_style_nonce' => $requestStyleNonce), $this->createRequest($requestNonceHeaders), $this->createResponse($responseNonceHeaders)),
            array($nonce, array('csp_script_nonce' => $requestScriptNonce, 'csp_style_nonce' => $requestStyleNonce), $this->createRequest($requestNonceHeaders), $this->createResponse()),
            array($nonce, array('csp_script_nonce' => $responseScriptNonce, 'csp_style_nonce' => $responseStyleNonce), $this->createRequest(), $this->createResponse($responseNonceHeaders)),
        );
    }

    public function provideRequestAndResponsesForOnKernelResponse()
    {
        $nonce = bin2hex(random_bytes(16));

        $requestScriptNonce = 'request-with-headers-script-nonce';
        $requestStyleNonce = 'request-with-headers-style-nonce';

        $responseScriptNonce = 'response-with-headers-script-nonce';
        $responseStyleNonce = 'response-with-headers-style-nonce';

        $requestNonceHeaders = array(
            'X-SymfonyProfiler-Script-Nonce' => $requestScriptNonce,
            'X-SymfonyProfiler-Style-Nonce' => $requestStyleNonce,
        );
        $responseNonceHeaders = array(
            'X-SymfonyProfiler-Script-Nonce' => $responseScriptNonce,
            'X-SymfonyProfiler-Style-Nonce' => $responseStyleNonce,
        );

        return array(
            array(
                $nonce,
                array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce),
                $this->createRequest(),
                $this->createResponse(),
                array('Content-Security-Policy' => null, 'Content-Security-Policy-Report-Only' => null, 'X-Content-Security-Policy' => null),
            ),
            array(
                $nonce, array('csp_script_nonce' => $requestScriptNonce, 'csp_style_nonce' => $requestStyleNonce),
                $this->createRequest($requestNonceHeaders),
                $this->createResponse($responseNonceHeaders),
                array('Content-Security-Policy' => null, 'Content-Security-Policy-Report-Only' => null, 'X-Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $requestScriptNonce, 'csp_style_nonce' => $requestStyleNonce),
                $this->createRequest($requestNonceHeaders),
                $this->createResponse(),
                array('Content-Security-Policy' => null, 'Content-Security-Policy-Report-Only' => null, 'X-Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $responseScriptNonce, 'csp_style_nonce' => $responseStyleNonce),
                $this->createRequest(),
                $this->createResponse($responseNonceHeaders),
                array('Content-Security-Policy' => null, 'Content-Security-Policy-Report-Only' => null, 'X-Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce),
                $this->createRequest(),
                $this->createResponse(array('Content-Security-Policy' => 'frame-ancestors https: ; form-action: https:', 'Content-Security-Policy-Report-Only' => 'frame-ancestors http: ; form-action: http:')),
                array('Content-Security-Policy' => 'frame-ancestors https: ; form-action: https:', 'Content-Security-Policy-Report-Only' => 'frame-ancestors http: ; form-action: http:', 'X-Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce),
                $this->createRequest(),
                $this->createResponse(array('Content-Security-Policy' => 'default-src \'self\' domain.com; script-src \'self\' \'unsafe-inline\'', 'Content-Security-Policy-Report-Only' => 'default-src \'self\' domain-report-only.com; script-src \'self\' \'unsafe-inline\'')),
                array('Content-Security-Policy' => 'default-src \'self\' domain.com; script-src \'self\' \'unsafe-inline\'; style-src \'self\' domain.com \'unsafe-inline\' \'nonce-'.$nonce.'\'', 'Content-Security-Policy-Report-Only' => 'default-src \'self\' domain-report-only.com; script-src \'self\' \'unsafe-inline\'; style-src \'self\' domain-report-only.com \'unsafe-inline\' \'nonce-'.$nonce.'\'', 'X-Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce),
                $this->createRequest(),
                $this->createResponse(array('Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\'')),
                array('Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\'', 'X-Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce),
                $this->createRequest(),
                $this->createResponse(array('Content-Security-Policy' => 'script-src \'self\'; style-src \'self\'')),
                array('Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\' \'nonce-'.$nonce.'\'; style-src \'self\' \'unsafe-inline\' \'nonce-'.$nonce.'\'', 'X-Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce),
                $this->createRequest(),
                $this->createResponse(array('X-Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\'')),
                array('X-Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\'', 'Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce),
                $this->createRequest(),
                $this->createResponse(array('X-Content-Security-Policy' => 'script-src \'self\'')),
                array('X-Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\' \'nonce-'.$nonce.'\'', 'Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce),
                $this->createRequest(),
                $this->createResponse(array('X-Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\' \'sha384-LALALALALAAL\'')),
                array('X-Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\' \'sha384-LALALALALAAL\' \'nonce-'.$nonce.'\'', 'Content-Security-Policy' => null),
            ),
            array(
                $nonce,
                array('csp_script_nonce' => $nonce, 'csp_style_nonce' => $nonce),
                $this->createRequest(),
                $this->createResponse(array('Content-Security-Policy' => 'script-src \'self\'; style-src \'self\'', 'X-Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\'; style-src \'self\'')),
                array('Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\' \'nonce-'.$nonce.'\'; style-src \'self\' \'unsafe-inline\' \'nonce-'.$nonce.'\'', 'X-Content-Security-Policy' => 'script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\' \'nonce-'.$nonce.'\''),
            ),
        );
    }

    private function createRequest(array $headers = array())
    {
        $request = new Request();
        $request->headers->add($headers);

        return $request;
    }

    private function createResponse(array $headers = array())
    {
        $response = new Response();
        $response->headers->add($headers);

        return $response;
    }

    private function mockNonceGenerator($value)
    {
        $generator = $this->getMockBuilder('Symfony\Bundle\WebProfilerBundle\Csp\NonceGenerator')->getMock();

        $generator->expects($this->any())
            ->method('generate')
            ->will($this->returnValue($value));

        return $generator;
    }
}
