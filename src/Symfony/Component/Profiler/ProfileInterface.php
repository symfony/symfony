<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler;

use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;


/**
 * Profile.
 *
 * @author Yosef Deray <yderay@gmail.com>
 */
interface ProfileInterface
{
    /**
     * Gets the token.
     *
     * @return string The token
     */
    public function getToken();

    /**
     * Returns the parent profile.
     *
     * @return Profile The parent profile
     */
    public function getParent();

    /**
     * Returns the name.
     *
     * @return string The name
     */
    public function getName();

    /**
     * Returns the time.
     *
     * @return string The time
     */
    public function getTime();

    /**
     * @return int
     */
    public function getStatusCode();

    /**
     * Finds children profilers.
     *
     * @return Profile[] An array of Profile
     */
    public function getChildren();

    /**
     * @return mixed
     */
    public function getType();

    /**
     * Sets children profiler.
     *
     * @param Profile[] $children An array of Profile
     */
    public function setChildren(array $children);

    /**
     * Adds the child token.
     *
     * @param Profile $child The child Profile
     */
    public function addChild(Profile $child);

    /**
     * Gets a Collector by name.
     *
     * @param string $name A collector name
     *
     * @return DataCollectorInterface A DataCollectorInterface instance
     *
     * @throws \InvalidArgumentException if the collector does not exist
     */
    public function getCollector($name);

    /**
     * Gets the Collectors associated with this profile.
     *
     * @return DataCollectorInterface[]
     */
    public function getCollectors();

    /**
     * Sets the Collectors associated with this profile.
     *
     * @param DataCollectorInterface[] $collectors
     */
    public function setCollectors(array $collectors);

    /**
     * Adds a Collector.
     *
     * @param DataCollectorInterface $collector A DataCollectorInterface instance
     */
    public function addCollector(DataCollectorInterface $collector);

    /**
     * Returns true if a Collector for the given name exists.
     *
     * @param string $name A collector name
     *
     * @return bool
     */
    public function hasCollector($name);
}