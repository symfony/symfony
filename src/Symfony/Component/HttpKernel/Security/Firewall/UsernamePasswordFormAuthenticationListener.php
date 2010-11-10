<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\Authentication\AuthenticationManagerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Authentication\Token\UsernamePasswordToken;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * UsernamePasswordFormAuthenticationListener is the default implementation of
 * an authentication via a simple form composed of a username and a password.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class UsernamePasswordFormAuthenticationListener extends FormAuthenticationListener
{
    /**
     * {@inheritdoc}
     */
    public function __construct(SecurityContext $securityContext, AuthenticationManagerInterface $authenticationManager, array $options = array(), LoggerInterface $logger = null)
    {
        parent::__construct($securityContext, $authenticationManager, array_merge(array(
            'username_parameter' => '_username',
            'password_parameter' => '_password',
            'post_only'          => true,
        ), $options), $logger);
    }

    /**
     * {@inheritdoc}
     */
    protected function attemptAuthentication(Request $request)
    {
        if ($this->options['post_only'] && 'post' !== strtolower($request->getMethod())) {
            if (null !== $this->logger) {
                $this->logger->debug(sprintf('Authentication method not supported: %s.', $request->getMethod()));
            }

            return null;
        }

        $username = trim($request->get($this->options['username_parameter']));
        $password = $request->get($this->options['password_parameter']);

        $request->getSession()->set(SecurityContext::LAST_USERNAME, $username);

        return $this->authenticationManager->authenticate(new UsernamePasswordToken($username, $password));
    }
}
