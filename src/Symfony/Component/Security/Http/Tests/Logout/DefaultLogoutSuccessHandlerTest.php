<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Logout;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Logout\DefaultLogoutSuccessHandler;

/**
 * @group legacy
 */
class DefaultLogoutSuccessHandlerTest extends TestCase
{
    public function testLogout()
    {
        $request = self::createMock(Request::class);
        $response = new RedirectResponse('/dashboard');

        $httpUtils = self::createMock(HttpUtils::class);
        $httpUtils->expects(self::once())
            ->method('createRedirectResponse')
            ->with($request, '/dashboard')
            ->willReturn($response);

        $handler = new DefaultLogoutSuccessHandler($httpUtils, '/dashboard');
        $result = $handler->onLogoutSuccess($request);

        self::assertSame($response, $result);
    }
}
