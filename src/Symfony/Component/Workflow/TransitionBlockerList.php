<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

/**
 * A list of transition blockers.
 */
class TransitionBlockerList implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /**
     * @var TransitionBlocker[]
     */
    private $blockers = array();

    /**
     * Creates a new transition blocker list.
     *
     * @param TransitionBlocker[] $blockers The transition blockers to add to the list
     */
    public function __construct(array $blockers = array())
    {
        foreach ($blockers as $blocker) {
            $this->add($blocker);
        }
    }

    /**
     * Converts the blocker into a string for debugging purposes.
     *
     * @return string The blocker as string
     */
    public function __toString()
    {
        $string = '';

        foreach ($this->blockers as $blocker) {
            $string .= $blocker."\n";
        }

        return $string;
    }

    /**
     * Adds a transition blocker to this list.
     *
     * @param TransitionBlocker $blocker
     */
    public function add(TransitionBlocker $blocker)
    {
        $this->blockers[] = $blocker;
    }

    /**
     * Merges an existing blocker list into this list.
     *
     * @param TransitionBlockerList $otherList
     */
    public function addAll(self $otherList)
    {
        foreach ($otherList as $blocker) {
            $this->blockers[] = $blocker;
        }
    }

    /**
     * Returns the blocker at a given offset.
     *
     * @param int $offset The offset of the blocker
     *
     * @return TransitionBlocker The blocker
     *
     * @throws \OutOfBoundsException if the offset does not exist
     */
    public function get($offset)
    {
        if (!isset($this->blockers[$offset])) {
            throw new \OutOfBoundsException(sprintf('The offset "%s" does not exist.', $offset));
        }

        return $this->blockers[$offset];
    }

    /**
     * Returns whether the given offset exists.
     *
     * @param int $offset The blocker offset
     *
     * @return bool Whether the offset exists
     */
    public function has($offset)
    {
        return isset($this->blockers[$offset]);
    }

    /**
     * Sets a blocker at a given offset.
     *
     * @param int                          $offset    The blocker offset
     * @param TransitionBlocker            $blocker   The blocker
     */
    public function set($offset, TransitionBlocker $blocker)
    {
        $this->blockers[$offset] = $blocker;
    }

    /**
     * Removes a blocker at a given offset.
     *
     * @param int $offset The offset to remove
     */
    public function remove($offset)
    {
        unset($this->blockers[$offset]);
    }

    /**
     * {@inheritdoc}
     *
     * @return \ArrayIterator|TransitionBlocker[]
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->blockers);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->blockers);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return $this->has($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $blocker)
    {
        if (null === $offset) {
            $this->add($blocker);
        } else {
            $this->set($offset, $blocker);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * Creates iterator for blockers with specific code.
     *
     * @param string $code The code to find
     *
     * @return TransitionBlocker|null The first blocker with the code.
     */
    public function findByCode(string $code)
    {
        foreach ($this as $transitionBlocker) {
            if ($transitionBlocker->getCode() === $code) {
                return $transitionBlocker;
            }
        }

        return null;
    }
}
