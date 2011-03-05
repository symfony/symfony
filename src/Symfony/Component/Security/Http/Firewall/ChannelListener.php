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

use Symfony\Component\Security\Http\AccessMap;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEventArgs;
use Symfony\Component\HttpKernel\Events;
use Doctrine\Common\EventManager;

/**
 * ChannelListener switches the HTTP protocol based on the access control
 * configuration.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ChannelListener implements ListenerInterface
{
    protected $map;
    protected $authenticationEntryPoint;
    protected $logger;

    public function __construct(AccessMap $map, AuthenticationEntryPointInterface $authenticationEntryPoint, LoggerInterface $logger = null)
    {
        $this->map = $map;
        $this->authenticationEntryPoint = $authenticationEntryPoint;
        $this->logger = $logger;
    }

    /**
     *
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
     * Handles channel management.
     *
     * @param RequestEventArgs $eventArgs A RequestEventArgs instance
     */
    public function onCoreSecurity(RequestEventArgs $eventArgs)
    {
        $request = $eventArgs->getRequest();

        list($attributes, $channel) = $this->map->getPatterns($request);

        if ('https' === $channel && !$request->isSecure()) {
            if (null !== $this->logger) {
                $this->logger->debug('Redirecting to HTTPS');
            }

            $response = $this->authenticationEntryPoint->start($eventArgs, $request);

            $eventArgs->setResponse($response);

            return;
        }

        if ('http' === $channel && $request->isSecure()) {
            if (null !== $this->logger) {
                $this->logger->debug('Redirecting to HTTP');
            }

            $response = $this->authenticationEntryPoint->start($eventArgs, $request);

            $eventArgs->setResponse($response);
        }
    }
}
