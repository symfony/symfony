<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EntryPoint;

use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * FormAuthenticationEntryPoint starts an authentication via a login form.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FormAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    protected $loginPath;
    protected $useForward;

    /**
     * Constructor
     *
     * @param string  $loginPath  The path to the login form
     * @param Boolean $useForward Whether to forward or redirect to the login form
     */
    public function __construct($loginPath, $useForward = false)
    {
        $this->loginPath = $loginPath;
        $this->useForward = (Boolean) $useForward;
    }

    /**
     * {@inheritdoc}
     */
    public function start(EventInterface $event, Request $request, AuthenticationException $authException = null)
    {
        if ($this->useForward) {
            return $event->getSubject()->handle(Request::create($this->loginPath), HttpKernelInterface::SUB_REQUEST);
        }

        return new RedirectResponse(0 !== strpos($this->loginPath, 'http') ? $request->getUriForPath($this->loginPath) : $this->loginPath, 302);
    }
}
