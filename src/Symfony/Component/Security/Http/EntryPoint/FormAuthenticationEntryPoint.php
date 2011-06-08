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
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * FormAuthenticationEntryPoint starts an authentication via a login form.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FormAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    private $loginPath;
    private $useForward;
    private $httpKernel;

    /**
     * Constructor
     *
     * @param HttpKernelInterface $kernel
     * @param string              $loginPath  The path to the login form
     * @param Boolean             $useForward Whether to forward or redirect to the login form
     */
    public function __construct(HttpKernelInterface $kernel, $loginPath, $useForward = false)
    {
        $this->httpKernel = $kernel;
        $this->loginPath = $loginPath;
        $this->useForward = (Boolean) $useForward;
    }

    /**
     * {@inheritdoc}
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $path = str_replace('{_locale}', $request->getSession()->getLocale(), $this->loginPath);
        if ($this->useForward) {
            $subRequest = Request::create($path, 'get', array(), $request->cookies->all(), array(), $request->server->all());

            return $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
        }

        return new RedirectResponse(0 !== strpos($path, 'http') ? $request->getUriForPath($path) : $path, 302);
    }
}
