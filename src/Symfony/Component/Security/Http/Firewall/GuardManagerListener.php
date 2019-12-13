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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Firewall\GuardAuthenticatorListenerTrait;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class GuardManagerListener
{
    use GuardAuthenticatorListenerTrait;

    private $authenticationManager;
    private $guardHandler;
    private $guardAuthenticators;
    protected $providerKey;
    protected $logger;

    /**
     * @param AuthenticatorInterface[] $guardAuthenticators
     */
    public function __construct(AuthenticationManagerInterface $authenticationManager, GuardAuthenticatorHandler $guardHandler, iterable $guardAuthenticators, string $providerKey, ?LoggerInterface $logger = null)
    {
        $this->authenticationManager = $authenticationManager;
        $this->guardHandler = $guardHandler;
        $this->guardAuthenticators = $guardAuthenticators;
        $this->providerKey = $providerKey;
        $this->logger = $logger;
    }

    public function __invoke(RequestEvent $requestEvent)
    {
        $request = $requestEvent->getRequest();
        $guardAuthenticators = $this->getSupportingGuardAuthenticators($request);
        if (!$guardAuthenticators) {
            return;
        }

        $this->executeGuardAuthenticators($guardAuthenticators, $requestEvent);
    }

    protected function getGuardKey(string $key): string
    {
        // Guard authenticators in the GuardAuthenticationManager are already indexed
        // by an unique key
        return $key;
    }
}
