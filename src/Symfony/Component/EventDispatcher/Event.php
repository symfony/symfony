<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * Event.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Event implements EventInterface
{
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
     * {@inheritDoc}
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * {@inheritDoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritDoc}
     */
    public function setProcessed()
    {
        $this->processed = true;
    }

    /**
     * {@inheritDoc}
     */
    public function isProcessed()
    {
        return $this->processed;
    }

    /**
     * {@inheritDoc}
     */
    public function all()
    {
        return $this->parameters;
    }

    /**
     * {@inheritDoc}
     */
    public function has($name)
    {
        return array_key_exists($name, $this->parameters);
    }

    /**
     * {@inheritDoc}
     */
    public function get($name)
    {
        if (!array_key_exists($name, $this->parameters)) {
            throw new \InvalidArgumentException(sprintf('The event "%s" has no "%s" parameter.', $this->name, $name));
        }

        return $this->parameters[$name];
    }

    /**
     * {@inheritDoc}
     */
    public function set($name, $value)
    {
        $this->parameters[$name] = $value;
    }
}
