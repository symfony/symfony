<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Stopwatch;

use Symfony\Contracts\Service\ResetInterface;

// Help opcache.preload discover always-needed symbols
class_exists(Section::class);

/**
 * Stopwatch provides a way to profile code.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Stopwatch implements ResetInterface
{
    public const ROOT = '__root__';

    /**
     * @var Section[]
     */
    private array $sections;

    /**
     * @var Section[]
     */
    private array $activeSections;

    /**
     * @param bool $morePrecision If true, time is stored as float to keep the original microsecond precision
     */
    public function __construct(
        private bool $morePrecision = false,
    ) {
        $this->reset();
    }

    /**
     * @return Section[]
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Creates a new section or re-opens an existing section.
     *
     * @param string|null $id The id of the session to re-open, null to create a new one
     *
     * @throws \LogicException When the section to re-open is not reachable
     */
    public function openSection(?string $id = null): void
    {
        $current = end($this->activeSections);

        if (null !== $id && null === $current->get($id)) {
            throw new \LogicException(\sprintf('The section "%s" has been started at an other level and cannot be opened.', $id));
        }

        $this->start('__section__.child', 'section');
        $this->activeSections[] = $current->open($id);
        $this->start('__section__');
    }

    /**
     * Stops the last started section.
     *
     * The id parameter is used to retrieve the events from this section.
     *
     * @see getSectionEvents()
     *
     * @throws \LogicException When there's no started section to be stopped
     */
    public function stopSection(string $id): void
    {
        $this->stop('__section__');

        if (1 == \count($this->activeSections)) {
            throw new \LogicException('There is no started section to stop.');
        }

        $this->sections[$id] = array_pop($this->activeSections)->setId($id);
        $this->stop('__section__.child');
    }

    /**
     * Starts an event.
     */
    public function start(string $name, ?string $category = null): StopwatchEvent
    {
        return end($this->activeSections)->startEvent($name, $category);
    }

    /**
     * Checks if the event was started.
     */
    public function isStarted(string $name): bool
    {
        return end($this->activeSections)->isEventStarted($name);
    }

    /**
     * Stops an event.
     */
    public function stop(string $name): StopwatchEvent
    {
        return end($this->activeSections)->stopEvent($name);
    }

    /**
     * Stops then restarts an event.
     */
    public function lap(string $name): StopwatchEvent
    {
        return end($this->activeSections)->stopEvent($name)->start();
    }

    /**
     * Returns a specific event by name.
     */
    public function getEvent(string $name): StopwatchEvent
    {
        return end($this->activeSections)->getEvent($name);
    }

    /**
     * Gets all events for a given section.
     *
     * @return StopwatchEvent[]
     */
    public function getSectionEvents(string $id): array
    {
        return isset($this->sections[$id]) ? $this->sections[$id]->getEvents() : [];
    }

    /**
     * Gets all events for the root section.
     *
     * @return StopwatchEvent[]
     */
    public function getRootSectionEvents(): array
    {
        return $this->sections[self::ROOT]->getEvents() ?? [];
    }

    /**
     * Resets the stopwatch to its original state.
     */
    public function reset(): void
    {
        $this->sections = $this->activeSections = [self::ROOT => new Section(null, $this->morePrecision)];
    }
}
