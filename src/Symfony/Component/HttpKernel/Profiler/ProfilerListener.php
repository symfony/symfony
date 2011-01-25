<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

/**
 * ProfilerListener collects data for the current request by listening to the core.response event.
 *
 * The handleException method must be connected to the core.exception event.
 * The handleResponse method must be connected to the core.response event.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class ProfilerListener
{
    protected $profiler;
    protected $exception;
    protected $onlyException;
    protected $matcher;

    /**
     * Constructor.
     *
     * @param Profiler                $profiler      A Profiler instance
     * @param RequestMatcherInterface $matcher       A RequestMatcher instance
     * @param Boolean                 $onlyException true if the profiler only collects data when an exception occurs, false otherwise
     */
    public function __construct(Profiler $profiler, RequestMatcherInterface $matcher = null, $onlyException = false)
    {
        $this->profiler = $profiler;
        $this->matcher = $matcher;
        $this->onlyException = $onlyException;
    }

    /**
     * Handles the core.exception event.
     *
     * @param EventInterface $event An EventInterface instance
     */
    public function handleException(EventInterface $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return false;
        }

        $this->exception = $event->get('exception');

        return false;
    }

    /**
     * Handles the core.response event.
     *
     * @param EventInterface $event An EventInterface instance
     *
     * @return Response $response A Response instance
     */
    public function handleResponse(EventInterface $event, Response $response)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->get('request_type')) {
            return $response;
        }

        if (null !== $this->matcher && !$this->matcher->matches($event->get('request'))) {
            return $response;
        }

        if ($this->onlyException && null === $this->exception) {
            return $response;
        }

        $this->profiler->collect($event->get('request'), $response, $this->exception);
        $this->exception = null;

        return $response;
    }
}
