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

use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEventArgs;
use Symfony\Component\HttpKernel\Events;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Doctrine\Common\EventManager;

/**
 * AnonymousAuthenticationListener automatically addds a Token if none is
 * already present.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AnonymousAuthenticationListener implements ListenerInterface
{
    protected $context;
    protected $key;
    protected $logger;

    public function __construct(SecurityContextInterface $context, $key, LoggerInterface $logger = null)
    {
        $this->context = $context;
        $this->key     = $key;
        $this->logger  = $logger;
    }

    /**
     * Registers a onCoreSecurity listener to load the SecurityContext from the
     * session.
     *
     * @param EventManager $evm An EventManager instance
     */
    public function register(EventManager $evm)
    {
        $evm->addEventListener(Events::onCoreSecurity, $this);
    }

    /**
     * {@inheritDoc}
     */
    public function unregister(EventManager $evm)
    {
    }

    /**
     * Handles anonymous authentication.
     *
     * @param RequestEventArgs $eventArgs A RequestEventArgs instance
     */
    public function onCoreSecurity(RequestEventArgs $eventArgs)
    {
        if (null !== $this->context->getToken()) {
            return;
        }

        $this->context->setToken(new AnonymousToken($this->key, 'anon.', array()));

        if (null !== $this->logger) {
            $this->logger->debug(sprintf('Populated SecurityContext with an anonymous Token'));
        }
    }
}
