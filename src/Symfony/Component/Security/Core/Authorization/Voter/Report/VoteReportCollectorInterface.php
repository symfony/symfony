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
interface VoteReportCollectorInterface extends \Traversable, \Countable, \ArrayAccess
{
    /**
     * Adds a vote report to this collector.
     *
     * @param VoteReportInterface $report The report to add
     */
    public function add(VoteReportInterface $report);

    /**
     * Returns the report at a given offset.
     *
     * @param int $offset The offset of the report
     *
     * @return VoteReportInterface The report
     *
     * @throws \OutOfBoundsException if the offset does not exist
     */
    public function get($offset);

    /**
     * Returns whether the given offset exists.
     *
     * @param int $offset The report offset
     *
     * @return bool Whether the offset exists
     */
    public function has($offset);

    /**
     * Sets a report at a given offset.
     *
     * @param int                 $offset The report offset
     * @param VoteReportInterface $report The report
     */
    public function set($offset, VoteReportInterface $report);

    /**
     * Removes a report at a given offset.
     *
     * @param int $offset The offset to remove
     */
    public function remove($offset);
}
