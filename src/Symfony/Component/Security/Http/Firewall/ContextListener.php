<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Firewall;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEventArgs;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedAccountException;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\User\AccountInterface;
use Doctrine\Common\EventManager;

/**
 * ContextListener manages the SecurityContext persistence through a session.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class ContextListener implements ListenerInterface
{
    protected $context;
    protected $contextKey;
    protected $logger;
    protected $userProviders;

    public function __construct(SecurityContext $context, array $userProviders, $contextKey, LoggerInterface $logger = null)
    {
        if (empty($contextKey)) {
            throw new \InvalidArgumentException('$contextKey must not be empty.');
        }

        $this->context = $context;
        $this->userProviders = $userProviders;
        $this->contextKey = $contextKey;
        $this->logger = $logger;
    }

    /**
     * Registers a onCoreSecurity listener to load the SecurityContext from the
     * session.
     *
     * @param EventManager $evm An EventManager instance
     */
    public function register(EventManager $evm)
    {
        $evm->addEventListener(
            array(Events::onCoreSecurity, Events::filterCoreResponse),
            $this
        );
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(EventManager $evm)
    {
        $evm->removeEventListener(Events::filterCoreResponse, $this);
    }

    /**
     * Reads the SecurityContext from the session.
     *
     * @param RequestEventArgs $eventArgs A RequestEventArgs instance
     */
    public function onCoreSecurity(RequestEventArgs $eventArgs)
    {
        $request = $eventArgs->getRequest();

        $session = $request->hasSession() ? $request->getSession() : null;

        if (null === $session || null === $token = $session->get('_security_'.$this->contextKey)) {
            $this->context->setToken(null);
        } else {
            if (null !== $this->logger) {
                $this->logger->debug('Read SecurityContext from the session');
            }

            $token = unserialize($token);

            if (null !== $token && false === $token->isImmutable()) {
                $token = $this->refreshUser($token);
            }

            $this->context->setToken($token);
        }
    }

    /**
     * Writes the SecurityContext to the session.
     *
     * @param EventInterface $event An EventInterface instance
     */
    public function filterCoreResponse(RequestEventArgs $eventArgs)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $eventArgs->getRequestType()) {
            return;
        }

        if (null === $token = $this->context->getToken()) {
            return;
        }

        if (null === $token || $token instanceof AnonymousToken) {
            return;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Write SecurityContext in the session');
        }

        $eventArgs->getRequest()->getSession()->set('_security_'.$this->contextKey, serialize($token));
    }

    /**
     * Refreshes the user by reloading it from the user provider
     *
     * @param TokenInterface $token
     *
     * @return TokenInterface|null
     */
    protected function refreshUser(TokenInterface $token)
    {
        $user = $token->getUser();
        if (!$user instanceof AccountInterface) {
            return $token;
        }

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Reloading user from user provider.'));
        }

        foreach ($this->userProviders as $provider) {
            try {
                $cUser = $provider->loadUserByAccount($user);

                $token->setRoles($cUser->getRoles());
                $token->setUser($cUser);

                if (false === $cUser->equals($user)) {
                    $token->setAuthenticated(false);
                }

                if (null !== $this->logger) {
                    $this->logger->debug(sprintf('Username "%s" was reloaded from user provider.', $user));
                }

                return $token;
            } catch (UnsupportedAccountException $unsupported) {
                // let's try the next user provider
            } catch (UsernameNotFoundException $notFound) {
                if (null !== $this->logger) {
                    $this->logger->debug(sprintf('Username "%s" could not be found.', $user));
                }

                return null;
            }
        }

        throw new \RuntimeException(sprintf('There is no user provider for user "%s".', get_class($user)));
    }
}
