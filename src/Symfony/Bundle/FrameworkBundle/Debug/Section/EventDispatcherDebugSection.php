<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Helper\DescriptorHelper;
use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ServiceProviderInterface;

/**
 * The "Events" tab of the interactive "debug" command.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class EventDispatcherDebugSection extends AbstractDebugSection
{
    private const string DEFAULT_DISPATCHER = 'event_dispatcher';

    public function __construct(
        private readonly ContainerInterface $dispatchers,
    ) {
    }

    public function getLabel(): string
    {
        return 'Events';
    }

    public function getShortLabel(): string
    {
        return 'Events';
    }

    public function describe(DebugItem $item, int $width): string
    {
        [$dispatcherName, $event] = explode(self::VALUE_SEPARATOR, $item->value, 2);

        if (!$this->dispatchers->has($dispatcherName)) {
            return \sprintf('Event dispatcher "%s" is not available.', $dispatcherName);
        }

        $dispatcher = $this->dispatchers->get($dispatcherName);
        if (!$dispatcher instanceof EventDispatcherInterface) {
            return \sprintf('Service "%s" is not an event dispatcher.', $dispatcherName);
        }

        return $this->describeToBuffer(static function (SymfonyStyle $io) use ($dispatcher, $dispatcherName, $event): void {
            $options = [
                'event' => $event,
                'format' => 'txt',
                'output' => $io,
            ];

            if (self::DEFAULT_DISPATCHER !== $dispatcherName) {
                $options['dispatcher_service_name'] = $dispatcherName;
            }

            (new DescriptorHelper())->describe($io, $dispatcher, $options);
        });
    }

    /**
     * Builds the full, unfiltered item list once. Recomputing it on every keystroke
     * would be costly on large applications.
     *
     * @return list<DebugItem>
     */
    protected function buildItems(): array
    {
        $items = [];
        $dispatcherNames = $this->getDispatcherNames();
        foreach ($dispatcherNames as $dispatcherName) {
            if (!$this->dispatchers->has($dispatcherName)) {
                continue;
            }

            $dispatcher = $this->dispatchers->get($dispatcherName);
            if (!$dispatcher instanceof EventDispatcherInterface) {
                continue;
            }

            $events = $dispatcher->getListeners();
            ksort($events);

            foreach ($events as $event => $listeners) {
                $label = self::DEFAULT_DISPATCHER === $dispatcherName ? $event : \sprintf('%s (%s)', $event, $dispatcherName);
                $items[] = new DebugItem('event', $dispatcherName.self::VALUE_SEPARATOR.$event, $label, searchText: $this->buildSearchText($dispatcher, $event, $listeners));
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function getDispatcherNames(): array
    {
        if ($this->dispatchers instanceof ServiceProviderInterface) {
            $names = array_keys($this->dispatchers->getProvidedServices());
        } else {
            $names = $this->dispatchers->has(self::DEFAULT_DISPATCHER) ? [self::DEFAULT_DISPATCHER] : [];
        }

        usort($names, static fn (string $a, string $b): int => (self::DEFAULT_DISPATCHER === $b) <=> (self::DEFAULT_DISPATCHER === $a) ?: $a <=> $b);

        return array_values(array_unique($names));
    }

    /**
     * The dispatcher name and event are omitted here because they are already
     * matched through the item's value and label (see DebugItem::matches()).
     *
     * @param callable[] $listeners
     */
    private function buildSearchText(EventDispatcherInterface $dispatcher, string $event, array $listeners): ?string
    {
        $parts = [];

        foreach ($listeners as $listener) {
            $parts[] = $this->formatCallable($listener);
            $parts[] = (string) $dispatcher->getListenerPriority($event, $listener);
        }

        return $parts ? implode("\n", $parts) : null;
    }

    private function formatCallable(callable $callable): string
    {
        if (\is_array($callable)) {
            $class = \is_object($callable[0]) ? $callable[0]::class : $callable[0];

            return $class.'::'.$callable[1];
        }

        if (\is_object($callable)) {
            return $callable::class;
        }

        return (string) $callable;
    }
}
