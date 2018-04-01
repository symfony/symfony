<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\EntryPoint;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Core\Exception\AuthenticationException;
use Symphony\Component\Security\Http\HttpUtils;
use Symphony\Component\HttpKernel\HttpKernelInterface;

/**
 * FormAuthenticationEntryPoint starts an authentication via a login form.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class FormAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    private $loginPath;
    private $useForward;
    private $httpKernel;
    private $httpUtils;

    /**
     * @param HttpKernelInterface $kernel
     * @param HttpUtils           $httpUtils  An HttpUtils instance
     * @param string              $loginPath  The path to the login form
     * @param bool                $useForward Whether to forward or redirect to the login form
     */
    public function __construct(HttpKernelInterface $kernel, HttpUtils $httpUtils, string $loginPath, bool $useForward = false)
    {
        $this->httpKernel = $kernel;
        $this->httpUtils = $httpUtils;
        $this->loginPath = $loginPath;
        $this->useForward = $useForward;
    }

    /**
     * {@inheritdoc}
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        if ($this->useForward) {
            $subRequest = $this->httpUtils->createRequest($request, $this->loginPath);

            $response = $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
            if (200 === $response->getStatusCode()) {
                $response->setStatusCode(401);
            }

            return $response;
        }

        return $this->httpUtils->createRedirectResponse($request, $this->loginPath);
    }
}
