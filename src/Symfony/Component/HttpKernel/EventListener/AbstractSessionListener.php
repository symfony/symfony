<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\EventListener;

use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\UnexpectedSessionUsageException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sets the session onto the request on the "kernel.request" event and saves
 * it on the "kernel.response" event.
 *
 * In addition, if the session has been started it overrides the Cache-Control
 * header in such a way that all caching is disabled in that case.
 * If you have a scenario where caching responses with session information in
 * them makes sense, you can disable this behaviour by setting the header
 * AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER on the response.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @internal
 */
abstract class AbstractSessionListener implements EventSubscriberInterface
{
    const NO_AUTO_CACHE_CONTROL_HEADER = 'Symfony-Session-NoAutoCacheControl';

    protected $container;
    private $sessionUsageStack = [];
    private $debug;

    public function __construct(ContainerInterface $container = null, bool $debug = false)
    {
        $this->container = $container;
        $this->debug = $debug;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $session = null;
        $request = $event->getRequest();
        if ($request->hasSession()) {
            // no-op
        } elseif (method_exists($request, 'setSessionFactory')) {
            $request->setSessionFactory(function () { return $this->getSession(); });
        } elseif ($session = $this->getSession()) {
            $request->setSession($session);
        }

        $session = $session ?? ($this->container && $this->container->has('initialized_session') ? $this->container->get('initialized_session') : null);
        $this->sessionUsageStack[] = $session instanceof Session ? $session->getUsageIndex() : 0;
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $response = $event->getResponse();
        $autoCacheControl = !$response->headers->has(self::NO_AUTO_CACHE_CONTROL_HEADER);
        // Always remove the internal header if present
        $response->headers->remove(self::NO_AUTO_CACHE_CONTROL_HEADER);

        if (!$session = $this->container && $this->container->has('initialized_session') ? $this->container->get('initialized_session') : $event->getRequest()->getSession()) {
            return;
        }

        if ($session->isStarted()) {
            /*
             * Saves the session, in case it is still open, before sending the response/headers.
             *
             * This ensures several things in case the developer did not save the session explicitly:
             *
             *  * If a session save handler without locking is used, it ensures the data is available
             *    on the next request, e.g. after a redirect. PHPs auto-save at script end via
             *    session_register_shutdown is executed after fastcgi_finish_request. So in this case
             *    the data could be missing the next request because it might not be saved the moment
             *    the new request is processed.
             *  * A locking save handler (e.g. the native 'files') circumvents concurrency problems like
             *    the one above. But by saving the session before long-running things in the terminate event,
             *    we ensure the session is not blocked longer than needed.
             *  * When regenerating the session ID no locking is involved in PHPs session design. See
             *    https://bugs.php.net/61470 for a discussion. So in this case, the session must
             *    be saved anyway before sending the headers with the new session ID. Otherwise session
             *    data could get lost again for concurrent requests with the new ID. One result could be
             *    that you get logged out after just logging in.
             *
             * This listener should be executed as one of the last listeners, so that previous listeners
             * can still operate on the open session. This prevents the overhead of restarting it.
             * Listeners after closing the session can still work with the session as usual because
             * Symfonys session implementation starts the session on demand. So writing to it after
             * it is saved will just restart it.
             */
            $session->save();
        }

        if ($session instanceof Session ? $session->getUsageIndex() === end($this->sessionUsageStack) : !$session->isStarted()) {
            return;
        }

        if ($autoCacheControl) {
            $response
                ->setExpires(new \DateTime())
                ->setPrivate()
                ->setMaxAge(0)
                ->headers->addCacheControlDirective('must-revalidate');
        }

        if (!$event->getRequest()->attributes->get('_stateless', false)) {
            return;
        }

        if ($this->debug) {
            throw new UnexpectedSessionUsageException('Session was used while the request was declared stateless.');
        }

        if ($this->container->has('logger')) {
            $this->container->get('logger')->warning('Session was used while the request was declared stateless.');
        }
    }

    public function onFinishRequest(FinishRequestEvent $event)
    {
        if ($event->isMasterRequest()) {
            array_pop($this->sessionUsageStack);
        }
    }

    public function onSessionUsage(): void
    {
        if (!$this->debug) {
            return;
        }

        if (!$requestStack = $this->container && $this->container->has('request_stack') ? $this->container->get('request_stack') : null) {
            return;
        }

        $stateless = false;
        $clonedRequestStack = clone $requestStack;
        while (null !== ($request = $clonedRequestStack->pop()) && !$stateless) {
            $stateless = $request->attributes->get('_stateless');
        }

        if (!$stateless) {
            return;
        }

        if (!$session = $this->container && $this->container->has('initialized_session') ? $this->container->get('initialized_session') : $requestStack->getCurrentRequest()->getSession()) {
            return;
        }

        if ($session->isStarted()) {
            $session->save();
        }

        throw new UnexpectedSessionUsageException('Session was used while the request was declared stateless.');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 128],
            // low priority to come after regular response listeners, but higher than StreamedResponseListener
            KernelEvents::RESPONSE => ['onKernelResponse', -1000],
            KernelEvents::FINISH_REQUEST => ['onFinishRequest'],
        ];
    }

    /**
     * Gets the session object.
     *
     * @return SessionInterface|null A SessionInterface instance or null if no session is available
     */
    abstract protected function getSession();
}
