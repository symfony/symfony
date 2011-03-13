<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Profiler;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEventArgs;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * ProfilerListener collects data for the current request by listening to the filterCoreResponse event.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProfilerListener
{
    protected $container;
    protected $exception;
    protected $onlyException;
    protected $matcher;

    /**
     * Constructor.
     *
     * @param ContainerInterface      $container     A ContainerInterface instance
     * @param RequestMatcherInterface $matcher       A RequestMatcher instance
     * @param Boolean                 $onlyException true if the profiler only collects data when an exception occurs, false otherwise
     */
    public function __construct(ContainerInterface $container, RequestMatcherInterface $matcher = null, $onlyException = false)
    {
        $this->container = $container;
        $this->matcher = $matcher;
        $this->onlyException = $onlyException;
    }

    /**
     * Handles the onCoreException event.
     *
     * @param ExceptionEventArgs $eventArgs An ExceptionEventArgs instance
     */
    public function onCoreException(ExceptionEventArgs $eventArgs)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $eventArgs->getRequestType()) {
            return;
        }

        $this->exception = $eventArgs->getException();
    }

    /**
     * Handles the filterCoreResponse event.
     *
     * @param FilterResponseEventArgs $eventArgs A FilterResponseEventArgs instance
     */
    public function filterCoreResponse(FilterResponseEventArgs $eventArgs)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $eventArgs->getRequestType()) {
            return;
        }

        if (null !== $this->matcher && !$this->matcher->matches($eventArgs->getRequest())) {
            return;
        }

        if ($this->onlyException && null === $this->exception) {
            return;
        }

        $this->container->get('profiler')->collect($eventArgs->getRequest(), $response, $this->exception);
        $this->exception = null;
    }
}
