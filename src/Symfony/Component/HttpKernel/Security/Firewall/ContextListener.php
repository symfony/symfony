<?php

namespace Symfony\Component\HttpKernel\Security\Firewall;

use Symfony\Component\Security\SecurityContext;
use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Authentication\Token\AnonymousToken;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ContextListener manages the SecurityContext persistence through a session.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ContextListener
{
    protected $context;
    protected $logger;

    public function __construct(SecurityContext $context, LoggerInterface $logger = null)
    {
        $this->context = $context;
        $this->logger = $logger;
    }

    /**
     * Registers a core.security listener to load the SecurityContext from the
     * session.
     *
     * @param EventDispatcher $dispatcher An EventDispatcher instance
     * @param integer         $priority   The priority
     */
    public function register(EventDispatcher $dispatcher, $priority = 0)
    {
        $dispatcher->connect('core.security', array($this, 'read'), $priority);
        $dispatcher->connect('core.response', array($this, 'write'), $priority);
    }

    /**
     * Reads the SecurityContext from the session.
     *
     * @param Event $event An Event instance
     */
    public function read(Event $event)
    {
        $request = $event->get('request');

        $session = $request->hasSession() ? $request->getSession() : null;

        if (null === $session || null === $token = $session->get('_security')) {
            $this->context->setToken(null);
        } else {
            if (null !== $this->logger) {
                $this->logger->debug('Read SecurityContext from the session');
            }

            $token = unserialize($token);

            $this->context->setToken($token);

            // FIXME: If the user is not an object, it probably means that it is persisted with a DAO
            // we need to load it now (that does not happen right now as the Token serialize the user
            // even if it is an object -- see Token)
        }
    }

    /**
     * Writes the SecurityContext to the session.
     *
     * @param Event $event An Event instance
     */
    public function write(Event $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return $response;
        }

        if (null === $token = $this->context->getToken()) {
            return $response;
        }

        if (null === $token || $token instanceof AnonymousToken) {
            return $response;
        }

        if (null !== $this->logger) {
            $this->logger->debug('Write SecurityContext in the session');
        }

        $event->get('request')->getSession()->set('_security', serialize($token));

        return $response;
    }
}
