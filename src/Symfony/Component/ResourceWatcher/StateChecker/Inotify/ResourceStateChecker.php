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

use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\ResourceWatcher\StateChecker\StateCheckerInterface;

/**
 * Abstract resource state checker.
 *
 * @author Yaroslav Kiliba <om.dattaya@gmail.com>
 */
abstract class ResourceStateChecker implements StateCheckerInterface
{
    /**
     * @var int Watch descriptor
     */
    protected $id;

    /**
     * @var int Inotify event
     */
    protected $event;

    /**
     * @var CheckerBag
     */
    private $bag;

    /**
     * @var int
     */
    private $eventsMask;

    /**
     * @var ResourceInterface
     */
    private $resource;

    /**
     * Initializes checker.
     *
     * @param CheckerBag        $bag
     * @param ResourceInterface $resource
     * @param int               $eventsMask
     */
    public function __construct(CheckerBag $bag, ResourceInterface $resource, $eventsMask = FilesystemEvent::ALL)
    {
        $this->resource   = $resource;
        $this->eventsMask = $eventsMask;
        $this->bag = $bag;
        $this->watch();
    }

    /**
     * Returns tracked resource.
     *
     * @return ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Returns watch descriptor
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Allows to set event for resource itself or for child resources.
     *
     * @param  int    $mask
     * @param  string $name
     */
    abstract public function setEvent($mask, $name = '');

    /**
     * Returns events mask for checker.
     *
     * @return int
     */
    protected function getEventsMask()
    {
        return $this->eventsMask;
    }

    /**
     * @return CheckerBag
     */
    protected function getBag()
    {
        return $this->bag;
    }

    /**
     * Starts to track current resource
     */
    protected function watch()
    {
        if ($this->id) {
            $this->bag->remove($this);
        }

        $this->id = $this->addWatch();
        $this->bag->add($this);
    }

    /**
     * Watch resource
     *
     * @return int
     */
    protected function addWatch()
    {
        return inotify_add_watch($this->getBag()->getInotify(), (string) $this->getResource(), $this->getInotifyEventMask());
    }

    /**
     * Unwatch resource
     *
     * @param int $id Watch descriptor
     */
    protected function unwatch($id)
    {
        @inotify_rm_watch($this->bag->getInotify(), $id);
    }

    /**
     * Transforms inotify event to FilesystemEvent event
     *
     * @param  int      $mask
     *
     * @return bool|int Returns event only if the checker supports it.
     */
    protected function fromInotifyMask($mask)
    {
        $mask &= ~IN_ISDIR;
        $event = 0;
        switch ($mask) {
            case (IN_MODIFY):
            case (IN_ATTRIB):
                $event =  FilesystemEvent::MODIFY;
                break;
            case (IN_CREATE):
                $event =  FilesystemEvent::CREATE;
                break;
            case (IN_DELETE):
            case (IN_IGNORED):
                $event =  FilesystemEvent::DELETE;
        }

        return $this->supportsEvent($event) ? $event : false;
    }

    /**
     * Checks whether checker supports provided resource event.
     *
     * @param  int  $event
     *
     * @return bool
     */
    protected function supportsEvent($event)
    {
        return 0 !== ($this->eventsMask & $event);
    }

    /**
     * Inotify event mask for inotify_add_watch
     *
     * @return int
     */
    protected function getInotifyEventMask()
    {
        return IN_MODIFY | IN_ATTRIB | IN_DELETE | IN_CREATE | IN_MOVE | IN_MOVE_SELF;
    }

    /**
     * Returns true if it is a directory mask
     *
     * @param  int  $mask
     *
     * @return bool
     */
    protected function isDir($mask)
    {
        return 0 !== ($mask & IN_ISDIR);
    }

    /**
     * Returns true if it is a mask with a IN_DELETE bit active
     *
     * @param  int  $mask
     *
     * @return bool
     */
    protected function isDeleted($mask)
    {
        return 0 !== ($mask & IN_DELETE);
    }

    /**
     * Returns true if it is a IN_IGNORED mask
     *
     * @param  int  $mask
     *
     * @return bool
     */
    protected function isIgnored($mask)
    {
        return IN_IGNORED === $mask;
    }

    /**
     * Returns true if it is a IN_MOVE_SELF mask
     *
     * @param  int  $mask
     *
     * @return bool
     */
    protected function isMoved($mask)
    {
        return IN_MOVE_SELF === $mask;
    }

    /**
     * Returns true if it is a mask with a IN_CREATE bit active
     *
     * @param  int  $mask
     *
     * @return bool
     */
    protected function isCreated($mask)
    {
        return 0 !== ($mask & IN_CREATE);
    }

    /**
     * Returns true if it is a mask with a IN_MOVED_FROM bit active
     *
     * @param  int  $mask
     *
     * @return bool
     */
    protected function isMovedFrom($mask)
    {
        return 0 !== ($mask & IN_MOVED_FROM);
    }

    /**
     * Returns true if it is a mask with a IN_MOVED_TO bit active
     *
     * @param  int  $mask
     *
     * @return bool
     */
    protected function isMovedTo($mask)
    {
        return 0 !== ($mask & IN_MOVED_TO);
    }
}
