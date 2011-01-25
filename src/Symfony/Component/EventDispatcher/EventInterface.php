<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * EventInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface EventInterface
{
    /**
     * Returns the subject.
     *
     * @return mixed The subject
     */
    function getSubject();

    /**
     * Returns the event name.
     *
     * @return string The event name
     */
    function getName();

    /**
     * Sets the return value for this event.
     *
     * @param mixed $value The return value
     */
    function setReturnValue($value);

    /**
     * Returns the return value.
     *
     * @return mixed The return value
     */
    function getReturnValue();

    /**
     * Sets the processed flag.
     *
     * @param Boolean $processed The processed flag value
     */
    function setProcessed($processed);

    /**
     * Returns whether the event has been processed by a listener or not.
     *
     * @return Boolean true if the event has been processed, false otherwise
     */
    function isProcessed();

    /**
     * Returns the event parameters.
     *
     * @return array The event parameters
     */
    function all();

    /**
     * Returns true if the parameter exists.
     *
     * @param  string  $name  The parameter name
     *
     * @return Boolean true if the parameter exists, false otherwise
     */
    function has($name);

    /**
     * Returns a parameter value.
     *
     * @param  string  $name  The parameter name
     *
     * @return mixed  The parameter value
     *
     * @throws \InvalidArgumentException When parameter doesn't exists for this event
     */
    function get($name);

    /**
     * Sets a parameter.
     *
     * @param string  $name   The parameter name
     * @param mixed   $value  The parameter value
     */
    function set($name, $value);
}
