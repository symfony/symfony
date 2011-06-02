<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Session;

/**
 * SessionInformationIterator.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
class SessionInformationIterator implements \Iterator, \ArrayAccess
{
    protected $sessions = array();
    protected $position = 0;

    /**
     * Iterator interface.
     *
     * @return void
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * Iterator interface.
     *
     * @return SessionInformation
     */
    public function current()
    {
        return $this->sessions[$this->position];
    }

    /**
     * Iterator interface.
     *
     * @return integer
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * Iterator interface.
     *
     * @return void
     */
    public function next()
    {
        $this->position++;
    }

    /**
     * Iterator interface.
     *
     * @return boolean
     */
    public function valid()
    {
        return isset($this->sessions[$this->position]);
    }

    /**
     * ArrayAccess interface.
     *
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->sessions[$offset]);
    }

    /**
     * ArrayAccess interface.
     *
     * @return SessionInformation
     */
    public function offsetGet($offset)
    {
        return $this->sessions[$offset];
    }

    /**
     * ArrayAccess interface.
     *
     * @param integer $offset
     * @param SessionInformation $sessionInformation
     * @return void
     */
    public function offsetSet($offset, $sessionInformation)
    {
        if ($sessionInformation instanceof SessionInformation) {
            $this->sessions[$offset] = $sessionInformation;
        }
    }

    /**
     * ArrayAccess interface.
     *
     * @param integer $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->sessions[$offset]);
        }
    }

    /**
     * Adds a SessionInformation object to the iterator.
     *
     * @param SessionInformation $sessionInformation
     * @return void
     */
    public function add(SessionInformation $sessionInformation)
    {
        $this->sessions[] = $sessionInformation;
    }

    /**
     * Sorts the session informations by the last request date.
     *
     * @return void
     */
    function sort()
    {
        $sessionsSorted = array();
        foreach ($this->sessions as $session) {
            $sessionsSorted[$session->getLastRequest()->getTimestamp()] = $session;
        }
        krsort($sessionsSorted);

        $this->sessions = array_values($sessionsSorted);
    }

    /**
     * Returns the number of session information objects.
     *
     * @return integer
     */
    function count()
    {
        return count($this->sessions);
    }
}
