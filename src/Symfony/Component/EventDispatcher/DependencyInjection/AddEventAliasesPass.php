<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * This pass allows bundles to extend the list of event aliases, hot-path events, and no-preload events.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class AddEventAliasesPass implements CompilerPassInterface
{
    /**
     * @param array<string, string> $eventAliases
     * @param list<string>          $hotPathEvents
     * @param list<string>          $noPreloadEvents
     */
    public function __construct(
        private array $eventAliases = [],
        private array $hotPathEvents = [],
        private array $noPreloadEvents = [],
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if ($this->eventAliases) {
            $aliases = $container->hasParameter('event_dispatcher.event_aliases') ? $container->getParameter('event_dispatcher.event_aliases') : [];
            $container->setParameter('event_dispatcher.event_aliases', array_merge($aliases, $this->eventAliases));
        }

        if ($this->hotPathEvents) {
            $events = $container->hasParameter('event_dispatcher.hot_path_events') ? $container->getParameter('event_dispatcher.hot_path_events') : [];
            $container->setParameter('event_dispatcher.hot_path_events', array_merge($events, $this->hotPathEvents));
        }

        if ($this->noPreloadEvents) {
            $events = $container->hasParameter('event_dispatcher.no_preload_events') ? $container->getParameter('event_dispatcher.no_preload_events') : [];
            $container->setParameter('event_dispatcher.no_preload_events', array_merge($events, $this->noPreloadEvents));
        }
    }
}
