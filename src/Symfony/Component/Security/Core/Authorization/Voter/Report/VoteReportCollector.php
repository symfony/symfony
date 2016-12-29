<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter\Report;

/**
 * @author Maxime Perrimond <max.perrimond@gmail.com>
 */
final class VoteReportCollector implements \IteratorAggregate, VoteReportCollectorInterface
{
    /**
     * @var VoteReportInterface[]
     */
    private $reports = array();

    /**
     * {@inheritdoc}
     */
    public function add(VoteReportInterface $report)
    {
        $this->reports[] = $report;
    }

    /**
     * {@inheritdoc}
     */
    public function get($offset)
    {
        if (!isset($this->reports[$offset])) {
            throw new \OutOfBoundsException(sprintf('The offset "%s" does not exist.', $offset));
        }

        return $this->reports[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function has($offset)
    {
        return isset($this->reports[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function set($offset, VoteReportInterface $report)
    {
        $this->reports[$offset] = $report;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($offset)
    {
        unset($this->reports[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->reports);
    }

    /**
     * {@inheritdoc}
     *
     * @return \ArrayIterator|VoteReportInterface[]
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->reports);
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
    public function offsetSet($offset, $violation)
    {
        if (null === $offset) {
            $this->add($violation);
        } else {
            $this->set($offset, $violation);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }
}
