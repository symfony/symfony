<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Util;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\SecurityContextInterface;

/**
 * Extracts Security Errors from Request
 *
 * @author Boris Vujicic <boris.vujicic@gmail.com>
 */
class AuthenticationErrorHelper
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param bool $clearSession
     * @return string
     */
    public function getLastAuthenticationError($clearSession = true)
    {
        $request = $this->getRequest();
        $session = $request->getSession();

        if ($request->attributes->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
            $msg = $request->attributes->get(SecurityContextInterface::AUTHENTICATION_ERROR);
        } elseif ($session !== null && $session->has(SecurityContextInterface::AUTHENTICATION_ERROR)) {
            $msg = $session->get(SecurityContextInterface::AUTHENTICATION_ERROR);

            if ($clearSession) {
                $session->remove(SecurityContextInterface::AUTHENTICATION_ERROR);
            }

        }

        return $msg;
    }

    /**
     * @return string
     */
    public function getLastUsername()
    {
        $session = $this->getRequest()->getSession();

        return $session === null ? '' : $session->get(SecurityContextInterface::LAST_USERNAME);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Request
     * @throws \LogicException
     */
    private function getRequest()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \LogicException('Request should exist so it can be processed for error.');
        }

        return $request;
    }
} 