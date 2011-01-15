<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Security\EntryPoint;

use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\Security\Authentication\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * RetryAuthenticationEntryPoint redirects URL based on the configured scheme.
 *
 * This entry point is not intended to work with HTTP post requests.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RetryAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    protected $httpPort;
    protected $httpsPort;

    public function __construct($httpPort = 80, $httpsPort = 443)
    {
        $this->httpPort = $httpPort;
        $this->httpsPort = $httpsPort;
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        $scheme = $request->isSecure() ? 'http' : 'https';
        if ('http' === $scheme && 80 != $this->httpPort) {
            $port = ':'.$this->httpPort;
        } elseif ('https' === $scheme && 443 != $this->httpPort) {
            $port = ':'.$this->httpsPort;
        } else {
            $port = '';
        }

        $qs = $request->getQueryString();
        if (null !== $qs) {
            $qs = '?'.$qs;
        }

        $url = $scheme.'://'.$request->getHost().$port.$request->getScriptName().$request->getPathInfo().$qs;

        $response = new Response();
        $response->setRedirect($url, 301);

        return $response;
    }
}
