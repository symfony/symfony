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
    private $disallowRedirectLoop;

    /**
     * Constructor
     *
     * @param HttpKernelInterface $kernel
     * @param string              $loginPath  The path to the login form
     * @param Boolean             $useForward Whether to forward or redirect to the login form
     * @param Boolean             $disallowRedirectLoop Whether to throw an exception when a redirect loop is detected
     */
    public function __construct(HttpKernelInterface $kernel, $loginPath, $useForward = false, $disallowRedirectLoop = false)
    {
        $this->httpKernel = $kernel;
        $this->loginPath = $loginPath;
        $this->useForward = (Boolean) $useForward;
        $this->disallowRedirectLoop = $disallowRedirectLoop;
    }

    /**
     * {@inheritdoc}
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        if ($this->useForward) {
            return $this->httpKernel->handle(Request::create($this->loginPath), HttpKernelInterface::SUB_REQUEST);
        }

        $loginPath = 0 !== strpos($this->loginPath, 'http') ? $request->getUriForPath($this->loginPath) : $this->loginPath;

        if ($this->disallowRedirectLoop && $request->getUri() == $loginPath) {
            throw new \LogicException(sprintf('Redirect loop detected when trying to redirect to the login page. Be sure that the login URL (%s) is under a firewall that allows anonymous users.', $this->loginPath));
        }

        return new RedirectResponse($loginPath, 302);
    }
}
