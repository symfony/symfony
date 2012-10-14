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

use Symfony\Component\HttpKernel\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Negotiation\MatcherNegotiatorBuilderInterface;
use Symfony\Component\HttpKernel\Negotiation\AcceptHeadersNegotiatorBuilder;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\NegotiationFailureException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RequestContextAwareInterface;
use Symfony\Component\Routing\Matcher\NegotiatorMatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Initializes the context from the request and sets request attributes based on a matching route.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterListener implements EventSubscriberInterface
{
    private $matcher;
    private $context;
    private $logger;

    /**
     * @var MatcherNegotiatorBuilderInterface[]
     */
    private $matcherNegotiatorBuilders;

    /**
     * Constructor.
     *
     * @param UrlMatcherInterface|RequestMatcherInterface $matcher The Url or Request matcher
     * @param RequestContext|null                         $context The RequestContext (can be null when $matcher implements RequestContextAwareInterface)
     * @param LoggerInterface|null                        $logger  The logger
     * 
     * @throws \InvalidArgumentException
     */
    public function __construct($matcher, RequestContext $context = null, LoggerInterface $logger = null)
    {
        if (!$matcher instanceof UrlMatcherInterface && !$matcher instanceof RequestMatcherInterface) {
            throw new \InvalidArgumentException('Matcher must either implement UrlMatcherInterface or RequestMatcherInterface.');
        }

        if (null === $context && !$matcher instanceof RequestContextAwareInterface) {
            throw new \InvalidArgumentException('You must either pass a RequestContext or the matcher must implement RequestContextAwareInterface.');
        }

        $this->matcher = $matcher;
        $this->context = $context ?: $matcher->getContext();
        $this->logger = $logger;
        $this->matcherNegotiatorBuilders = array();
    }

    /**
     * @param MatcherNegotiatorBuilderInterface $builder
     */
    public function addMatcherNegotiatorBuilder(MatcherNegotiatorBuilderInterface $builder)
    {
        $this->matcherNegotiatorBuilders[] = $builder;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $request = $event->getRequest();

        // initialize the context that is also used by the generator (assuming matcher and generator share the same context instance)
        $this->context->fromRequest($request);

        $this->addMatcherNegotiatorBuilder(new AcceptHeadersNegotiatorBuilder($request->headers));

        if ($request->attributes->has('_controller')) {
            // routing is already done
            return;
        }

        if ($this->matcher instanceof NegotiatorMatcherInterface) {
            foreach ($this->matcherNegotiatorBuilders as $builder) {
                $builder->buildNegotiator($this->matcher->getNegotiator());
            }
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
                $this->logger->info(sprintf('Matched route "%s" (parameters: %s)', $parameters['_route'], $this->parametersToString($parameters)));
            }

            $request->attributes->add($parameters);
            unset($parameters['_route']);
            unset($parameters['_controller']);
            $request->attributes->set('_route_params', $parameters);
        } catch (ResourceNotFoundException $e) {
            $message = sprintf('No route found for "%s %s"', $request->getMethod(), $request->getPathInfo());

            throw new NotFoundHttpException($message, $e);
        } catch (MethodNotAllowedException $e) {
            $message = sprintf('No route found for "%s %s": Method Not Allowed (Allow: %s)', $request->getMethod(), $request->getPathInfo(), strtoupper(implode(', ', $e->getAllowedMethods())));

            throw new MethodNotAllowedHttpException($e->getAllowedMethods(), $message, $e);
        } catch (NegotiationFailureException $e) {
            $message = sprintf('No acceptable value found for "%s" parameters.', implode(', ', $e->getNegotiatedParameters()));


            throw new NotAcceptableHttpException($message, $e, array('Vary' => implode(', ', $this->getMatcherNegotiatorVaryingHeaders())));
        }
    }

    public function onKernelResponse(GetResponseEvent $event)
    {
        $headers = $event->getResponse()->headers;
        $vary = implode(', ', $this->getMatcherNegotiatorVaryingHeaders());

        if (strlen($vary) > 0) {
            $headers->set('Vary', $headers->has('Vary') ? $headers->get('Vary').', '.$vary : $vary);
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array(array('onKernelRequest', 32)),
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }

    private function parametersToString(array $parameters)
    {
        $pieces = array();
        foreach ($parameters as $key => $val) {
            $pieces[] = sprintf('"%s": "%s"', $key, (is_string($val) ? $val : json_encode($val)));
        }

        return implode(', ', $pieces);
    }

    private function getMatcherNegotiatorVaryingHeaders()
    {
        $varyingHeaders = array();
        if ($this->matcher instanceof NegotiatorMatcherInterface) {
            foreach ($this->matcherNegotiatorBuilders as $builder) {
                $varyingHeaders = array_merge($varyingHeaders, $builder->getVaryingHeaders($this->matcher->getNegotiator()));
            }
        }

        return array_unique($varyingHeaders);
    }
}
