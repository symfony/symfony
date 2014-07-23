<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Login;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeServicesResolverInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

class LoginManager implements LoginManagerInterface
{
    /**
     * @var SecurityContextInterface
     */
    private $securityContext;

    /**
     * @var UserCheckerInterface
     */
    private $userChecker;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var SessionAuthenticationStrategyInterface
     */
    private $sessionAuthenticationStrategy;

    /**
     * @var RememberMeServicesResolverInterface
     */
    private $rememberMeServicesResolver;

    /**
     * @param SecurityContextInterface $securityContext
     * @param UserCheckerInterface $userChecker
     * @param RequestStack $requestStack
     * @param SessionAuthenticationStrategyInterface $sessionAuthenticationStrategy
     * @param RememberMeServicesResolverInterface $rememberMeServicesResolver
     */
    public function __construct(SecurityContextInterface $securityContext, UserCheckerInterface $userChecker, RequestStack $requestStack, SessionAuthenticationStrategyInterface $sessionAuthenticationStrategy, RememberMeServicesResolverInterface $rememberMeServicesResolver)
    {
        $this->securityContext = $securityContext;
        $this->userChecker = $userChecker;
        $this->requestStack = $requestStack;
        $this->sessionAuthenticationStrategy = $sessionAuthenticationStrategy;
        $this->rememberMeServicesResolver = $rememberMeServicesResolver;
    }

    /**
     * @param $firewallName
     * @param UserInterface $user
     * @param Response $response
     */
    public function loginUser($firewallName, UserInterface $user, Response $response = null)
    {
        $this->userChecker->checkPostAuth($user);
        $token = $this->createToken($firewallName, $user);

        $request = $this->requestStack->getMasterRequest();
        if (null !== $request) {
            $this->sessionAuthenticationStrategy->onAuthentication($request, $token);

            if (null !== $response) {
                $rememberMeServices = $this->rememberMeServicesResolver->resolve($firewallName);

                if (null !== $rememberMeServices) {
                    $rememberMeServices->loginSuccess($request, $response, $token);
                }
            }
        }

        $this->securityContext->setToken($token);
    }

    /**
     * @param $firewall
     * @param UserInterface $user
     * @return UsernamePasswordToken
     */
    protected function createToken($firewall, UserInterface $user)
    {
        return new UsernamePasswordToken($user, null, $firewall, $user->getRoles());
    }
}
