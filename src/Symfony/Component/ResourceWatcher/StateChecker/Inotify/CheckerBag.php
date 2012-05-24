<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\StateChecker\Inotify;

/**
 * Bag for the inotify resource state checkers.
 *
 * @author Yaroslav Kiliba <om.dattaya@gmail.com>
 */
class CheckerBag
{
    /**
     * @var \SplObjectStorage[]
     */
    protected $watched = array();

    /**
     * @var resource Inotify resource.
     */
    private $inotify;

    /**
     * Initializes bag.
     *
     * @param resource $inotify Inotify resource
     */
    public function __construct($inotify)
    {
        $this->inotify = $inotify;
    }

    /**
     * Adds state checker to the bag.
     *
     * @param ResourceStateChecker $watched
     */
    public function add(ResourceStateChecker $watched)
    {
        $id = $watched->getId();
        if (!isset($this->watched[$id])) {
            $this->watched[$id] = new \SplObjectStorage();
        }

        $this->watched[$id]->attach($watched);
    }

    /**
     * Returns state checker from the bag
     *
     * @param int $id Watch descriptor
     * @return \SplObjectStorage|array
     */
    public function get($id)
    {
        return isset($this->watched[$id]) ? $this->watched[$id] : array();
    }

    /**
     * Checks whether at least one state checker with id $id exists.
     *
     * @param int $id Watch descriptor
     * @return bool
     */
    public function has($id)
    {
        return isset($this->watched[$id]) && 0 !== $this->watched[$id]->count();
    }

    /**
     * @return resource Inotify resource
     */
    public function getInotify()
    {
        return $this->inotify;
    }

    /**
     * Removes state checker from the bag
     *
     * @param ResourceStateChecker $watched
     */
    public function remove(ResourceStateChecker $watched)
    {
        $this->watched[$watched->getId()]->detach($watched);
    }
}
