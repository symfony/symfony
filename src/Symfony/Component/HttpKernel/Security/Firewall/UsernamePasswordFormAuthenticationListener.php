<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\Security\Authentication\AuthenticationManagerInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Authentication\Token\UsernamePasswordToken;

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
