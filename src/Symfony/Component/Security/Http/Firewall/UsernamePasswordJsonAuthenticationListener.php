<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * UsernamePasswordJsonAuthenticationListener is an implementation of
 * an authentication via a JSON document composed of a username and a password.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class UsernamePasswordJsonAuthenticationListener extends AbstractAuthenticationListener
{
    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, SessionAuthenticationStrategyInterface $sessionStrategy, HttpUtils $httpUtils, $providerKey, AuthenticationSuccessHandlerInterface $successHandler, AuthenticationFailureHandlerInterface $failureHandler, array $options = array(), LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($tokenStorage, $authenticationManager, $sessionStrategy, $httpUtils, $providerKey, $successHandler, $failureHandler, array_merge(array(
            'username_parameter' => '_username',
            'password_parameter' => '_password',
        ), $options), $logger, $dispatcher);
    }

    /**
     * {@inheritdoc}
     */
    protected function attemptAuthentication(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data[$this->options['username_parameter']])) {
            throw new BadCredentialsException(sprintf('Missing key "%s".', $this->options['username_parameter']));
        }

        if (!isset($data[$this->options['password_parameter']])) {
            throw new BadCredentialsException(sprintf('Missing key "%s".', $this->options['password_parameter']));
        }

        $username = $data[$this->options['username_parameter']];
        if (!is_string($username)) {
            throw new BadCredentialsException(sprintf('The key "%s" must contain a string.', $this->options['username_parameter']));
        }

        if (strlen($username) > Security::MAX_USERNAME_LENGTH) {
            throw new BadCredentialsException('Invalid username.');
        }

        $password = $data[$this->options['password_parameter']];
        if (!is_string($password)) {
            throw new BadCredentialsException(sprintf('The key "%s" must contain a string.', $this->options['password_parameter']));
        }

        return $this->authenticationManager->authenticate(new UsernamePasswordToken($username, $password, $this->providerKey));
    }
}
