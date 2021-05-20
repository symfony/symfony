<?php

namespace Symfony\Component\Security\Http\Tests\EntryPoint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\EntryPoint\RouteAuthenticationEntryPoint;
use Symfony\Component\Security\Http\HttpUtils;

class RouteAuthenticationEntryPointTest extends TestCase
{
    public function testStart()
    {
        $request = new Request();

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils
            ->expects($this->once())
            ->method('generateUri')
            ->with($request, 'entry_point_path')
            ->willReturn('/my-login-url')
        ;

        $entryPoint = new RouteAuthenticationEntryPoint($httpUtils, 'entry_point_path');
        $response = $entryPoint->start($request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/my-login-url', $response->getTargetUrl());
    }
}
