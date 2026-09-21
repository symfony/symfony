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
use Symfony\Bundle\SecurityBundle\Controller\OidcLoginBackChannelLogoutController;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class OidcLoginBackChannelLogoutControllerTest extends TestCase
{
    public function testTheLogoutTokenOfTheRequestIsHandedToTheFirewall()
    {
        $backChannelLogout = self::createBackChannelLogout();

        $response = $this->createController($backChannelLogout)(self::createRequest('the.logout.token'), 'main');

        $this->assertSame(['the.logout.token'], $backChannelLogout->logoutTokens);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getContent());
        // Back-Channel Logout 1.0, Section 2.7
        $this->assertSame('no-cache, no-store, private', $response->headers->get('Cache-Control'));
    }

    public function testARequestWithoutALogoutTokenIsRefused()
    {
        $backChannelLogout = self::createBackChannelLogout();

        $response = $this->createController($backChannelLogout)(self::createRequest(null), 'main');

        $this->assertSame([], $backChannelLogout->logoutTokens);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'invalid_request', 'error_description' => 'The request carries no "logout_token" parameter.'], json_decode($response->getContent(), true));
    }

    /**
     * Section 2.9: the provider learns the token will do no better on another attempt.
     */
    public function testARefusedLogoutTokenIsReportedToTheProvider()
    {
        $backChannelLogout = self::createBackChannelLogout(new AuthenticationException('Invalid logout token: "nonce".'));

        $response = $this->createController($backChannelLogout)(self::createRequest('the.logout.token'), 'main');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'invalid_request', 'error_description' => 'Invalid logout token: "nonce".'], json_decode($response->getContent(), true));
    }

    public function testThrowsNotFoundForAFirewallWithoutBackChannelLogout()
    {
        $controller = new OidcLoginBackChannelLogoutController(new ServiceLocator([]));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Back-channel logout is not enabled for the "main" firewall.');

        $controller(self::createRequest('the.logout.token'), 'main');
    }

    private function createController(object $backChannelLogout): OidcLoginBackChannelLogoutController
    {
        return new OidcLoginBackChannelLogoutController(new ServiceLocator(['main' => static fn (): object => $backChannelLogout]));
    }

    /**
     * A stand-in for the OidcBackChannelLogout of a firewall, which is final and takes a
     * verifier, a discovery document and a cache pool to be built.
     */
    private static function createBackChannelLogout(?AuthenticationException $failure = null): object
    {
        return new class($failure) {
            /**
             * @var list<string>
             */
            public array $logoutTokens = [];

            public function __construct(
                private readonly ?AuthenticationException $failure,
            ) {
            }

            public function logout(string $logoutToken): void
            {
                $this->logoutTokens[] = $logoutToken;

                if (null !== $this->failure) {
                    throw $this->failure;
                }
            }
        };
    }

    private static function createRequest(?string $logoutToken): Request
    {
        return Request::create('/oidc/backchannel-logout', 'POST', null === $logoutToken ? [] : ['logout_token' => $logoutToken]);
    }
}
