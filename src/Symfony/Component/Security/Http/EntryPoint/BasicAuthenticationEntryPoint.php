<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EntryPoint;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\HttpBasicAuthenticator;

trigger_deprecation('symfony/security-http', '5.4', 'The "%s" class is deprecated, use the new security system with "%s" instead.', BasicAuthenticationEntryPoint::class, HttpBasicAuthenticator::class);

/**
 * BasicAuthenticationEntryPoint starts an HTTP Basic authentication.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since Symfony 5.4
 */
class BasicAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    private $realmName;

    public function __construct(string $realmName)
    {
        $this->realmName = $realmName;
    }

    /**
     * {@inheritdoc}
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $response = new Response();
        $response->headers->set('WWW-Authenticate', sprintf('Basic realm="%s"', $this->realmName));
        $response->setStatusCode(401);

        return $response;
    }
}
