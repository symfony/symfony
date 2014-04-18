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

/**
 * Stopwatch provides a way to profile code.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Stopwatch
{
    /**
     * @var Section[]
     */
    private $sections;

    /**
     * @var array
     */
    private $activeSections;

    public function __construct()
    {
        $this->sections = $this->activeSections = array('__root__' => new Section('__root__'));
    }

    /**
     * Creates a new section or re-opens an existing section.
     *
     * @param string|null $id The id of the session to re-open, null to create a new one
     *
     * @throws \LogicException When the section to re-open is not reachable
     */
    public function openSection($id = null)
    {
        $current = end($this->activeSections);

        if (null !== $id && null === $current->get($id)) {
            throw new \LogicException(sprintf('The section "%s" has been started at an other level and can not be opened.', $id));
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
     * @see getSectionEvents
     *
     * @param string $id The identifier of the section
     *
     * @throws \LogicException When there's no started section to be stopped
     */
    public function stopSection($id)
    {
        $this->stop('__section__');

        if (1 == count($this->activeSections)) {
            throw new \LogicException('There is no started section to stop.');
        }

        $this->sections[$id] = array_pop($this->activeSections)->setId($id);
        $this->stop('__section__.child');
    }

    /**
     * Starts an event.
     *
     * @param string $name     The event name
     * @param string $category The event category
     *
     * @return StopwatchEvent A StopwatchEvent instance
     */
    public function start($name, $category = null)
    {
        return end($this->activeSections)->startEvent($name, $category);
    }

    /**
     * Checks if the event was started
     *
     * @param string $name The event name
     *
     * @return bool
     */
    public function isStarted($name)
    {
        return end($this->activeSections)->isEventStarted($name);
    }

    /**
     * Stops an event.
     *
     * @param string $name The event name
     *
     * @return StopwatchEvent A StopwatchEvent instance
     */
    public function stop($name)
    {
        return end($this->activeSections)->stopEvent($name);
    }

    /**
     * Stops then restarts an event.
     *
     * @param string $name The event name
     *
     * @return StopwatchEvent A StopwatchEvent instance
     */
    public function lap($name)
    {
        return end($this->activeSections)->stopEvent($name)->start();
    }

    /**
     * Gets all events for a given section.
     *
     * @param string $id A section identifier
     *
     * @return StopwatchEvent[] An array of StopwatchEvent instances
     */
    public function getSectionEvents($id)
    {
        return isset($this->sections[$id]) ? $this->sections[$id]->getEvents() : array();
    }
}


/**
 * @internal This class is for internal usage only
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Section
{
    /**
     * @var StopwatchEvent[]
     */
    private $events = array();

    /**
     * @var null|float
     */
    private $origin;

    /**
     * @var string
     */
    private $id;

    /**
     * @var Section[]
     */
    private $children = array();

    /**
     * Constructor.
     *
     * @param float|null $origin Set the origin of the events in this section, use null to set their origin to their start time
     */
    public function __construct($origin = null)
    {
        $this->origin = is_numeric($origin) ? $origin : null;
    }

    /**
     * Returns the child section.
     *
     * @param string $id The child section identifier
     *
     * @return Section|null The child section or null when none found
     */
    public function get($id)
    {
        foreach ($this->children as $child) {
            if ($id === $child->getId()) {
                return $child;
            }
        }
    }

    /**
     * Creates or re-opens a child section.
     *
     * @param string|null $id null to create a new section, the identifier to re-open an existing one.
     *
     * @return Section A child section
     */
    public function open($id)
    {
        if (null === $session = $this->get($id)) {
            $session = $this->children[] = new self(microtime(true) * 1000);
        }

        return $session;
    }

    /**
     * @return string The identifier of the section
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the session identifier.
     *
     * @param string $id The session identifier
     *
     * @return Section The current section
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Starts an event.
     *
     * @param string $name     The event name
     * @param string $category The event category
     *
     * @return StopwatchEvent The event
     */
    public function startEvent($name, $category)
    {
        if (!isset($this->events[$name])) {
            $this->events[$name] = new StopwatchEvent($this->origin ?: microtime(true) * 1000, $category);
        }

        return $this->events[$name]->start();
    }

    /**
     * Checks if the event was started
     *
     * @param string $name The event name
     *
     * @return bool
     */
    public function isEventStarted($name)
    {
        return isset($this->events[$name]) && $this->events[$name]->isStarted();
    }

    /**
     * Stops an event.
     *
     * @param string $name The event name
     *
     * @return StopwatchEvent The event
     *
     * @throws \LogicException When the event has not been started
     */
    public function stopEvent($name)
    {
        if (!isset($this->events[$name])) {
            throw new \LogicException(sprintf('Event "%s" is not started.', $name));
        }

        return $this->events[$name]->stop();
    }

    /**
     * Stops then restarts an event.
     *
     * @param string $name The event name
     *
     * @return StopwatchEvent The event
     *
     * @throws \LogicException When the event has not been started
     */
    public function lap($name)
    {
        return $this->stopEvent($name)->start();
    }

    /**
     * Returns the events from this section.
     *
     * @return StopwatchEvent[] An array of StopwatchEvent instances
     */
    public function getEvents()
    {
        return $this->events;
    }
}
