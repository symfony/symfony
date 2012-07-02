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

use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;

/**
 * Directory state checker.
 *
 * @author Yaroslav Kiliba <om.dattaya@gmail.com>
 */
class DirectoryStateChecker extends ResourceStateChecker
{
    /**
     * @var DirectoryStateChecker[]
     */
    protected $directories = array();

    /**
     * @var FileResource[]
     */
    protected $files = array();

    /**
     * @var array File inotify events
     */
    protected $fileEvents = array();

    /**
     * @var array Dir inotify events
     */
    protected $dirEvents = array();

    /**
     * @var array It is used to track resource moving
     * @see DirectoryStateChecker::trackMoveEvent()
     */
    protected $movedResources = array();

    /**
     * @var string Key in the $movedResources array where to put name of the resource from next following move event.
     * @see DirectoryStateChecker::trackMoveEvent()
     */
    protected $lastMove;

    /**
     * @var bool
     */
    protected $isNew = false;

    /**
     * Initializes checker.
     *
     * @param CheckerBag        $bag
     * @param DirectoryResource $resource
     * @param int               $eventsMask
     */
    public function __construct(CheckerBag $bag, DirectoryResource $resource, $eventsMask = FilesystemEvent::ALL)
    {
        parent::__construct($bag, $resource, $eventsMask);

        $this->createChildCheckers();
    }

    /**
     * {@inheritdoc}
     */
    public function setEvent($mask, $name = '')
    {
        if ($this->isDir($mask)) {
            if (0 !== (IN_ATTRIB & $mask)) {
                return;
            }
            $this->dirEvents[$name] = $mask;
        } else {
            $this->fileEvents[$name] = $mask;
        }
        $this->trackMoveEvent($mask, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function getChangeset()
    {
        $this->event = isset($this->fileEvents['']) ? $this->fileEvents[''] : null;
        unset($this->fileEvents['']);

        $this->handleItself();

        $changeset = array();
        if ($this->event) {
            if ($event = $this->fromInotifyMask($this->event)) {
                $changeset[] = array(
                    'resource' => $this->getResource(),
                    'event'    => $event
                );
            }

            if ($this->isDeleted($this->event)) {
                $this->fileEvents = array_fill_keys(array_keys($this->files), IN_DELETE);
                $this->dirEvents  = array_fill_keys(array_keys($this->directories), IN_DELETE);
                $this->getBag()->remove($this);
                $this->id = null;
            }
        }
        $deleted = array();

        foreach ($this->movedResources as $key => $value) {
            if ($key === $value) {
                unset($this->dirEvents[$key]);
                unset($this->fileEvents[$key]);
            }
        }

        foreach ($this->dirEvents as $name => $event) {
            $normalized = $this->normalizeEvent($event);
            if (isset($this->directories[$name])) {
                $this->directories[$name]->setEvent($normalized);
                if ($this->isDeleted($normalized)) {
                    $deleted[] = $this->directories[$name];
                    unset($this->directories[$name]);
                }
            } elseif (!$this->isDeleted($normalized)) {
                $this->createNewDirectoryChecker($name);
            }
        }

        foreach ($this->fileEvents as $name => $event) {
            $normalized = $this->normalizeFileEvent($event, $name);
            if (($event = $this->fromInotifyMask($normalized)) && $this->files[$name] instanceof FileResource) {
                $changeset[] =
                    array(
                        'resource' => $this->files[$name],
                        'event'    => $event
                    );
            }
            if ($this->isDeleted($normalized)) {
                unset($this->files[$name]);
            }
        }

        $funct = function($checker) use (&$changeset) {
            foreach ($checker->getChangeset() as $change) {
                $changeset[] = $change;
            }
        };

        array_walk($this->directories, $funct);
        array_walk($deleted, $funct);

        $this->dirEvents = $this->fileEvents = $this->movedResources = array();
        $this->event     = null;

        return $changeset;
    }

    /**
     * Tracks move event. It is for situation when resource was roundtripped, e.g.
     * rename('dir', 'dir_new'); rename('dir_new', 'dir'). As a result no events should be returned.
     * This function just keeps track of the move events, and they're analyzed in the getChangeset method.
     *
     * @param int    $mask
     * @param string $name
     */
    protected function trackMoveEvent($mask, $name)
    {
        if ($this->isMovedFrom($mask)) {
            if ($key = array_search($name, $this->movedResources)) {
                $this->lastMove = $key;
            } else {
                $this->lastMove = $name;
            }
        } elseif ($this->isMovedTo($mask)) {
            $this->movedResources[$this->lastMove] = $name;
        } elseif ($key = array_search($name, $this->movedResources)) {
            unset($this->movedResources[$key]);
        }
    }

    /**
     * Handles event related to itself.
     */
    protected function handleItself()
    {
        if (!$this->isNew && $this->isCreated($this->event)) {
            $this->unwatch($this->id);
            $this->reindexChildCheckers();
            $this->event = null;
        }
        $this->isNew = false;
    }

    /**
     * Reads files and subdirectories and transforms them to resources.
     */
    protected function createChildCheckers()
    {
        foreach ($this->getResource()->getFilteredResources() as $resource) {
            $resource instanceof DirectoryResource
                ? $this->directories[basename((string) $resource)] = new DirectoryStateChecker($this->getBag(), $resource, $this->getEventsMask())
                : $this->files[basename((string) $resource)] = $resource;
        }
    }

    /**
     * Used in case the folder was deleted and than created again or situations like this.
     * It rescans the folder, files that was before get IN_MODIFY event, folders - IN_CREATE - to make them to rescan itself
     */
    protected function reindexChildCheckers()
    {
        $this->fileEvents = array_fill_keys(array_keys($this->files), IN_DELETE);
        $this->dirEvents  = array_fill_keys(array_keys($this->directories), IN_DELETE);
        foreach ($this->getResource()->getFilteredResources() as $resource) {
            $basename = basename((string) $resource);
            if ($resource instanceof FileResource) {
                if (isset($this->files[$basename])) {
                    $this->fileEvents[$basename] = IN_MODIFY;
                } else {
                    $this->files[$basename] = $resource;
                    $this->fileEvents[$basename] = 'new';
                }
            } else {
                isset($this->directories[$basename])
                    ? $this->dirEvents[$basename] = IN_CREATE
                    : $this->createNewDirectoryChecker($basename, $resource);
            }
        }
        $this->watch();
    }

    /**
     * Normalizes file event
     *
     * @param  int      $event
     * @param  string   $name
     *
     * @return null|int
     */
    protected function normalizeFileEvent($event, $name)
    {
        if ('new' === $event) {
            return IN_CREATE;
        }

        $event = $this->normalizeEvent($event);
        if (isset($this->files[$name])) {
            return $this->isCreated($event) ? IN_MODIFY : $event;
        }
        if (!$this->isDeleted($event)) {
            $this->createFileResource($name);

            return IN_CREATE;
        }

        return null;
    }

    /**
     * Normalizes event
     *
     * @param  int $event
     *
     * @return int
     */
    protected function normalizeEvent($event)
    {
        $event &= ~IN_ISDIR;
        if (0 !== ($event & IN_MOVED_FROM)) {
            return IN_DELETE;
        } elseif (0 !== ($event & IN_MOVED_TO)) {
            return IN_CREATE;
        }

        return $event;
    }

    /**
     * Creates new DirectoryStateChecker
     *
     * @param string                 $name
     * @param null|DirectoryResource $resource
     */
    protected function createNewDirectoryChecker($name, DirectoryResource $resource = null)
    {
        $resource = $resource ?: new DirectoryResource($this->getResource()->getResource().'/'.$name);
        $this->directories[$name] = new NewDirectoryStateChecker($this->getBag(), $resource, $this->getEventsMask());
    }

    /**
     * Creates new FileResource
     *
     * @param string $name
     */
    protected function createFileResource($name)
    {
        if ($this->getResource()->getPattern() && !preg_match($this->getResource()->getPattern(), $name)) {
            $this->files[$name] = 'skip';
        } else {
            $this->files[$name] = new FileResource($this->getResource()->getResource().'/'.$name);
        }
    }
}
