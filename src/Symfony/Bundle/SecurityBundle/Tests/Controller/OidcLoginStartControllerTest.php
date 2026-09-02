<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Controller\OidcLoginStartController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class OidcLoginStartControllerTest extends TestCase
{
    public function testDelegatesToTheFirewallAuthenticator()
    {
        $redirect = new RedirectResponse('https://provider.example.com/authorize');
        $authenticator = new class($redirect) implements AuthenticationEntryPointInterface {
            public ?Request $request = null;

            public function __construct(private readonly Response $response)
            {
            }

            public function start(Request $request, ?AuthenticationException $authException = null): Response
            {
                $this->request = $request;

                return $this->response;
            }
        };

        $controller = new OidcLoginStartController(new ServiceLocator(['main' => static fn () => $authenticator]));
        $request = new Request();

        $this->assertSame($redirect, $controller($request, 'main'));
        $this->assertSame($request, $authenticator->request);
    }

    public function testThrowsNotFoundForAnUnknownFirewall()
    {
        $controller = new OidcLoginStartController(new ServiceLocator([]));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('No "oidc_login" authenticator is registered for the "main" firewall.');

        $controller(new Request(), 'main');
    }
}
