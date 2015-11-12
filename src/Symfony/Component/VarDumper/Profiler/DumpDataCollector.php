<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Profiler;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;
use Symfony\Component\VarDumper\Dumper\TraceableDumper;

/**
 * DumpDataCollector.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class DumpDataCollector implements EventSubscriberInterface, DataCollectorInterface
{
    private $requestStack;
    private $dumper;
    private $responses;

    /**
     * Constructor.
     *
     * @param RequestStack      $requestStack   The RequestStack.
     * @param TraceableDumper   $dumper         The TraceableDumper.
     */
    public function __construct(RequestStack $requestStack, TraceableDumper $dumper)
    {
        $this->requestStack = $requestStack;
        $this->dumper = $dumper;
        $this->responses = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return;
        }

        // Sub-requests and programmatic calls stay in the collected profile.
        if ($this->requestStack->getMasterRequest() !== $request || $request->isXmlHttpRequest() || $request->headers->has('Origin')) {
            return;
        }

        if ( !isset($this->responses[$request]) ) {
            return;
        }

        $response = $this->responses[$request];

        // In all other conditions that remove the web debug toolbar, dumps are written on the output.
        if (
            !$response->headers->has('X-Debug-Token')
            || $response->isRedirection()
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
            || false === strripos($response->getContent(), '</body>')
        ) {
            $isHtml = ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'));
            $this->dumper->forceOutput($isHtml?'html':'cli');
        }

        return new DumpData($this->dumper->getData(), $this->dumper->getCharset());
    }

    /**
     * Remembers the response associated to each request.
     *
     * @param FilterResponseEvent $event The filter response event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $this->responses[$event->getRequest()] = $event->getResponse();
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => 'onKernelResponse',
        );
    }
}
