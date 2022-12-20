<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\EntryPoint;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\EntryPoint\FormAuthenticationEntryPoint;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * @group legacy
 */
class FormAuthenticationEntryPointTest extends TestCase
{
    public function testStart()
    {
        $request = self::createMock(Request::class);
        $response = new RedirectResponse('/the/login/path');

        $httpKernel = self::createMock(HttpKernelInterface::class);
        $httpUtils = self::createMock(HttpUtils::class);
        $httpUtils
            ->expects(self::once())
            ->method('createRedirectResponse')
            ->with(self::equalTo($request), self::equalTo('/the/login/path'))
            ->willReturn($response)
        ;

        $entryPoint = new FormAuthenticationEntryPoint($httpKernel, $httpUtils, '/the/login/path', false);

        self::assertEquals($response, $entryPoint->start($request));
    }

    public function testStartWithUseForward()
    {
        $request = self::createMock(Request::class);
        $subRequest = self::createMock(Request::class);
        $response = new Response('', 200);

        $httpUtils = self::createMock(HttpUtils::class);
        $httpUtils
            ->expects(self::once())
            ->method('createRequest')
            ->with(self::equalTo($request), self::equalTo('/the/login/path'))
            ->willReturn($subRequest)
        ;

        $httpKernel = self::createMock(HttpKernelInterface::class);
        $httpKernel
            ->expects(self::once())
            ->method('handle')
            ->with(self::equalTo($subRequest), self::equalTo(HttpKernelInterface::SUB_REQUEST))
            ->willReturn($response)
        ;

        $entryPoint = new FormAuthenticationEntryPoint($httpKernel, $httpUtils, '/the/login/path', true);

        $entryPointResponse = $entryPoint->start($request);

        self::assertEquals($response, $entryPointResponse);
        self::assertEquals(401, $entryPointResponse->getStatusCode());
    }
}
