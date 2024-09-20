<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

use Monolog\LogRecord;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds the current route information to the log entry.
 *
 * @author Piotr Stankowski <git@trakos.pl>
 *
 * @final
 */
class RouteProcessor implements EventSubscriberInterface, ResetInterface
{
    private array $routeData = [];

    public function __construct(
        private bool $includeParams = true,
    ) {
        $this->reset();
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->routeData && !isset($record->extra['requests'])) {
            $record->extra['requests'] = array_values($this->routeData);
        }

        return $record;
    }

    public function reset(): void
    {
        $this->routeData = [];
    }

    public function addRouteData(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->reset();
        }

        $request = $event->getRequest();
        if (!$request->attributes->has('_controller')) {
            return;
        }

        $currentRequestData = [
            'controller' => $request->attributes->get('_controller'),
            'route' => $request->attributes->get('_route'),
        ];

        if ($this->includeParams) {
            $currentRequestData['route_params'] = $request->attributes->get('_route_params');
        }

        $this->routeData[spl_object_id($request)] = $currentRequestData;
    }

    public function removeRouteData(FinishRequestEvent $event): void
    {
        $requestId = spl_object_id($event->getRequest());
        unset($this->routeData[$requestId]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['addRouteData', 1],
            KernelEvents::FINISH_REQUEST => ['removeRouteData', 1],
        ];
    }
}
