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

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderAdapter;
use Symfony\Component\Form\Extension\Csrf\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\SimpleFormAuthenticatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;
use Psr\Log\LoggerInterface;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class SimpleFormAuthenticationListener extends AbstractAuthenticationListener
{
    private $simpleAuthenticator;
    private $csrfTokenManager;

    /**
     * Constructor.
     *
     * @param TokenStorageInterface                  $tokenStorage          A TokenStorageInterface instance
     * @param AuthenticationManagerInterface         $authenticationManager An AuthenticationManagerInterface instance
     * @param SessionAuthenticationStrategyInterface $sessionStrategy
     * @param HttpUtils                              $httpUtils             An HttpUtils instance
     * @param string                                 $providerKey
     * @param AuthenticationSuccessHandlerInterface  $successHandler
     * @param AuthenticationFailureHandlerInterface  $failureHandler
     * @param array                                  $options               An array of options for the processing of a
     *                                                                      successful, or failed authentication attempt
     * @param LoggerInterface|null                   $logger                A LoggerInterface instance
     * @param EventDispatcherInterface|null          $dispatcher            An EventDispatcherInterface instance
     * @param CsrfTokenManagerInterface|null         $csrfTokenManager      A CsrfTokenManagerInterface instance
     * @param SimpleFormAuthenticatorInterface|null  $simpleAuthenticator   A SimpleFormAuthenticatorInterface instance
     *
     * @throws \InvalidArgumentException In case no simple authenticator is provided
     * @throws InvalidArgumentException  In case an invalid CSRF token manager is passed
     */
    public function __construct(TokenStorageInterface $tokenStorage, AuthenticationManagerInterface $authenticationManager, SessionAuthenticationStrategyInterface $sessionStrategy, HttpUtils $httpUtils, $providerKey, AuthenticationSuccessHandlerInterface $successHandler, AuthenticationFailureHandlerInterface $failureHandler, array $options = array(), LoggerInterface $logger = null, EventDispatcherInterface $dispatcher = null, $csrfTokenManager = null, SimpleFormAuthenticatorInterface $simpleAuthenticator = null)
    {
        if (!$simpleAuthenticator) {
            throw new \InvalidArgumentException('Missing simple authenticator');
        }

        if ($csrfTokenManager instanceof CsrfProviderInterface) {
            $csrfTokenManager = new CsrfProviderAdapter($csrfTokenManager);
        } elseif (null !== $csrfTokenManager && !$csrfTokenManager instanceof CsrfTokenManagerInterface) {
            throw new InvalidArgumentException('The CSRF token manager should be an instance of CsrfProviderInterface or CsrfTokenManagerInterface.');
        }

        if (isset($options['intention'])) {
            if (isset($options['csrf_token_id'])) {
                throw new \InvalidArgumentException(sprintf('You should only define an option for one of "intention" or "csrf_token_id" for the "%s". Use the "csrf_token_id" as it replaces "intention".', __CLASS__));
            }

            @trigger_error('The "intention" option for the '.__CLASS__.' is deprecated since version 2.8 and will be removed in 3.0. Use the "csrf_token_id" option instead.', E_USER_DEPRECATED);

            $options['csrf_token_id'] = $options['intention'];
        }

        $this->simpleAuthenticator = $simpleAuthenticator;
        $this->csrfTokenManager = $csrfTokenManager;

        $options = array_merge(array(
            'username_parameter' => '_username',
            'password_parameter' => '_password',
            'csrf_parameter' => '_csrf_token',
            'csrf_token_id' => 'authenticate',
            'post_only' => true,
        ), $options);

        parent::__construct($tokenStorage, $authenticationManager, $sessionStrategy, $httpUtils, $providerKey, $successHandler, $failureHandler, $options, $logger, $dispatcher);
    }

    /**
     * {@inheritdoc}
     */
    protected function requiresAuthentication(Request $request)
    {
        if ($this->options['post_only'] && !$request->isMethod('POST')) {
            return false;
        }

        return parent::requiresAuthentication($request);
    }

    /**
     * {@inheritdoc}
     */
    protected function attemptAuthentication(Request $request)
    {
        if (null !== $this->csrfTokenManager) {
            $csrfToken = ParameterBagUtils::getRequestParameterValue($request, $this->options['csrf_parameter']);

            if (false === $this->csrfTokenManager->isTokenValid(new CsrfToken($this->options['csrf_token_id'], $csrfToken))) {
                throw new InvalidCsrfTokenException('Invalid CSRF token.');
            }
        }

        if ($this->options['post_only']) {
            $username = trim(ParameterBagUtils::getParameterBagValue($request->request, $this->options['username_parameter']));
            $password = ParameterBagUtils::getParameterBagValue($request->request, $this->options['password_parameter']);
        } else {
            $username = trim(ParameterBagUtils::getRequestParameterValue($request, $this->options['username_parameter']));
            $password = ParameterBagUtils::getRequestParameterValue($request, $this->options['password_parameter']);
        }

        if (strlen($username) > Security::MAX_USERNAME_LENGTH) {
            throw new BadCredentialsException('Invalid username.');
        }

        $request->getSession()->set(Security::LAST_USERNAME, $username);

        $token = $this->simpleAuthenticator->createToken($request, $username, $password, $this->providerKey);

        return $this->authenticationManager->authenticate($token);
    }
}
