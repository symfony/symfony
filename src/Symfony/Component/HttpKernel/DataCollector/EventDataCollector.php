<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\DataCollector;

use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class EventDataCollector extends DataCollector implements LateDataCollectorInterface
{
    protected $dispatcher;
    private $requestStack;
    private $currentRequest;

    public function __construct(?EventDispatcherInterface $dispatcher = null, ?RequestStack $requestStack = null)
    {
        $this->dispatcher = $dispatcher;
        $this->requestStack = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, ?\Throwable $exception = null)
    {
        $this->currentRequest = $this->requestStack && $this->requestStack->getMainRequest() !== $request ? $request : null;
        $this->data = [
            'called_listeners' => [],
            'not_called_listeners' => [],
            'orphaned_events' => [],
        ];
    }

    public function reset()
    {
        $this->data = [];

        if ($this->dispatcher instanceof ResetInterface) {
            $this->dispatcher->reset();
        }
    }

    public function lateCollect()
    {
        if ($this->dispatcher instanceof TraceableEventDispatcher) {
            $this->setCalledListeners($this->dispatcher->getCalledListeners($this->currentRequest));
            $this->setNotCalledListeners($this->dispatcher->getNotCalledListeners($this->currentRequest));
            $this->setOrphanedEvents($this->dispatcher->getOrphanedEvents($this->currentRequest));
        }

        $this->data = $this->cloneVar($this->data);
    }

    /**
     * @param array $listeners An array of called listeners
     *
     * @see TraceableEventDispatcher
     */
    public function setCalledListeners(array $listeners)
    {
        $this->data['called_listeners'] = $listeners;
    }

    /**
     * @see TraceableEventDispatcher
     *
     * @return array|Data
     */
    public function getCalledListeners()
    {
        return $this->data['called_listeners'];
    }

    /**
     * @see TraceableEventDispatcher
     */
    public function setNotCalledListeners(array $listeners)
    {
        $this->data['not_called_listeners'] = $listeners;
    }

    /**
     * @see TraceableEventDispatcher
     *
     * @return array|Data
     */
    public function getNotCalledListeners()
    {
        return $this->data['not_called_listeners'];
    }

    /**
     * @param array $events An array of orphaned events
     *
     * @see TraceableEventDispatcher
     */
    public function setOrphanedEvents(array $events)
    {
        $this->data['orphaned_events'] = $events;
    }

    /**
     * @see TraceableEventDispatcher
     *
     * @return array|Data
     */
    public function getOrphanedEvents()
    {
        return $this->data['orphaned_events'];
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'events';
    }
}
