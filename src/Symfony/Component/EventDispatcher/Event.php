<?php

namespace Symfony\Component\EventDispatcher;

/*
 * This file is part of the Symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Event.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Event
{
    protected $value = null;
    protected $processed = false;
    protected $subject;
    protected $name;
    protected $parameters;

    /**
     * Constructs a new Event.
     *
     * @param mixed   $subject      The subject
     * @param string  $name         The event name
     * @param array   $parameters   An array of parameters
     */
    public function __construct($subject, $name, $parameters = array())
    {
        $this->subject = $subject;
        $this->name = $name;
        $this->parameters = $parameters;
    }

    /**
     * Returns the subject.
     *
     * @return mixed The subject
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Returns the event name.
     *
     * @return string The event name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the return value for this event.
     *
     * @param mixed $value The return value
     */
    public function setReturnValue($value)
    {
        $this->value = $value;
    }

    /**
     * Returns the return value.
     *
     * @return mixed The return value
     */
    public function getReturnValue()
    {
        return $this->value;
    }

    /**
     * Sets the processed flag.
     *
     * @param Boolean $processed The processed flag value
     */
    public function setProcessed($processed)
    {
        $this->processed = (boolean) $processed;
    }

    /**
     * Returns whether the event has been processed by a listener or not.
     *
     * @return Boolean true if the event has been processed, false otherwise
     */
    public function isProcessed()
    {
        return $this->processed;
    }

    /**
     * Returns the event parameters.
     *
     * @return array The event parameters
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * Returns true if the parameter exists.
     *
     * @param  string  $name  The parameter name
     *
     * @return Boolean true if the parameter exists, false otherwise
     */
    public function has($name)
    {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * Returns a parameter value.
     *
     * @param  string  $name  The parameter name
     *
     * @return mixed  The parameter value
     *
     * @throws \InvalidArgumentException When parameter doesn't exists for this event
     */
    public function get($name)
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new \InvalidArgumentException(sprintf('The event "%s" has no "%s" parameter.', $this->name, $name));
        }

        return $this->parameters[$name];
    }

    /**
     * Sets a parameter.
     *
     * @param string  $name   The parameter name
     * @param mixed   $value  The parameter value
     */
    public function set($name, $value)
    {
        $this->parameters[$name] = $value;
    }
}
