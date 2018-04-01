<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\EventListener;

use Psr\Log\LoggerInterface;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpKernel\Event\GetResponseEvent;
use Symphony\Component\HttpKernel\Event\FinishRequestEvent;
use Symphony\Component\HttpKernel\Kernel;
use Symphony\Component\HttpKernel\KernelEvents;
use Symphony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symphony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symphony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symphony\Component\HttpFoundation\RequestStack;
use Symphony\Component\Routing\Exception\MethodNotAllowedException;
use Symphony\Component\Routing\Exception\NoConfigurationException;
use Symphony\Component\Routing\Exception\ResourceNotFoundException;
use Symphony\Component\Routing\Matcher\UrlMatcherInterface;
use Symphony\Component\Routing\Matcher\RequestMatcherInterface;
use Symphony\Component\Routing\RequestContext;
use Symphony\Component\Routing\RequestContextAwareInterface;
use Symphony\Component\EventDispatcher\EventSubscriberInterface;
use Symphony\Component\HttpFoundation\Request;

/**
 * Initializes the context from the request and sets request attributes based on a matching route.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class RouterListener implements EventSubscriberInterface
{
    private $matcher;
    private $context;
    private $logger;
    private $requestStack;
    private $projectDir;
    private $debug;

    /**
     * @param UrlMatcherInterface|RequestMatcherInterface $matcher      The Url or Request matcher
     * @param RequestStack                                $requestStack A RequestStack instance
     * @param RequestContext|null                         $context      The RequestContext (can be null when $matcher implements RequestContextAwareInterface)
     * @param LoggerInterface|null                        $logger       The logger
     * @param string                                      $projectDir
     * @param bool                                        $debug
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($matcher, RequestStack $requestStack, RequestContext $context = null, LoggerInterface $logger = null, string $projectDir = null, bool $debug = true)
    {
        if (!$matcher instanceof UrlMatcherInterface && !$matcher instanceof RequestMatcherInterface) {
            throw new \InvalidArgumentException('Matcher must either implement UrlMatcherInterface or RequestMatcherInterface.');
        }

        if (null === $context && !$matcher instanceof RequestContextAwareInterface) {
            throw new \InvalidArgumentException('You must either pass a RequestContext or the matcher must implement RequestContextAwareInterface.');
        }

        $this->matcher = $matcher;
        $this->context = $context ?: $matcher->getContext();
        $this->requestStack = $requestStack;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
        $this->debug = $debug;
    }

    private function setCurrentRequest(Request $request = null)
    {
        if (null !== $request) {
            try {
                $this->context->fromRequest($request);
            } catch (\UnexpectedValueException $e) {
                throw new BadRequestHttpException($e->getMessage(), $e, $e->getCode());
            }
        }
    }

    /**
     * After a sub-request is done, we need to reset the routing context to the parent request so that the URL generator
     * operates on the correct context again.
     *
     * @param FinishRequestEvent $event
     */
    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        $this->setCurrentRequest($this->requestStack->getParentRequest());
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        $this->setCurrentRequest($request);

        if ($request->attributes->has('_controller')) {
            // routing is already done
            return;
        }

        // add attributes based on the request (routing)
        try {
            // matching a request is more powerful than matching a URL path + context, so try that first
            if ($this->matcher instanceof RequestMatcherInterface) {
                $parameters = $this->matcher->matchRequest($request);
            } else {
                $parameters = $this->matcher->match($request->getPathInfo());
            }

            if (null !== $this->logger) {
                $this->logger->info('Matched route "{route}".', array(
                    'route' => isset($parameters['_route']) ? $parameters['_route'] : 'n/a',
                    'route_parameters' => $parameters,
                    'request_uri' => $request->getUri(),
                    'method' => $request->getMethod(),
                ));
            }

            $request->attributes->add($parameters);
            unset($parameters['_route'], $parameters['_controller']);
            $request->attributes->set('_route_params', $parameters);
        } catch (ResourceNotFoundException $e) {
            if ($this->debug && $e instanceof NoConfigurationException) {
                $event->setResponse($this->createWelcomeResponse());

                return;
            }

            $message = sprintf('No route found for "%s %s"', $request->getMethod(), $request->getPathInfo());

            if ($referer = $request->headers->get('referer')) {
                $message .= sprintf(' (from "%s")', $referer);
            }

            throw new NotFoundHttpException($message, $e);
        } catch (MethodNotAllowedException $e) {
            $message = sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->getMethod(), $request->getPathInfo(), implode(', ', $e->getAllowedMethods()));

            throw new MethodNotAllowedHttpException($e->getAllowedMethods(), $message, $e);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(array('onKernelRequest', 32)),
            KernelEvents::FINISH_REQUEST => array(array('onKernelFinishRequest', 0)),
        );
    }

    private function createWelcomeResponse()
    {
        $version = Kernel::VERSION;
        $baseDir = realpath($this->projectDir).DIRECTORY_SEPARATOR;
        $docVersion = substr(Kernel::VERSION, 0, 3);

        ob_start();
        include __DIR__.'/../Resources/welcome.html.php';

        return new Response(ob_get_clean(), Response::HTTP_NOT_FOUND);
    }
}
