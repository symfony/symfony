<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\StateChecker;

use Symfony\Component\ResourceWatcher\Event\Event;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;

/**
 * Recursive directory state checker.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class DirectoryStateChecker extends ResourceStateChecker
{
    private $childs = array();

    /**
     * Initializes checker.
     *
     * @param   DirectoryResource   $resource
     */
    public function __construct(DirectoryResource $resource)
    {
        parent::__construct($resource);

        foreach ($this->createDirectoryChildCheckers($resource) as $checker) {
            $this->childs[$checker->getResource()->getId()] = $checker;
        }
    }

    /**
     * Check tracked resource for changes.
     *
     * @return  array
     */
    public function getChangeset()
    {
        $changeset = parent::getChangeset();
        if (count($changeset) && Event::MODIFIED === $changeset[0]['event']) {
            $changeset = array();
        }

        foreach ($this->childs as $id => $checker) {
            foreach ($checker->getChangeset() as $change) {
                if (Event::DELETED === $change['event'] && $id === $change['resource']->getId()) {
                    unset($this->childs[$id]);
                }
                $changeset[] = $change;
            }
        }

        if ($this->getResource()->exists()) {
            foreach ($this->createDirectoryChildCheckers($this->getResource()) as $checker) {
                if (!isset($this->childs[$checker->getResource()->getId()])) {
                    $this->childs[$checker->getResource()->getId()] = $checker;
                    $changeset[] = array(
                        'event' => Event::CREATED, 'resource' => $checker->getResource()
                    );
                }
            }
        }

        return $changeset;
    }

    /**
     * Reads files and subdirectories on provided resource path and transform them to resources.
     *
     * @param   DirectoryResource   $resource
     *
     * @return  array
     */
    private function createDirectoryChildCheckers(DirectoryResource $resource)
    {
        $checkers = array();
        foreach ($resource->getFilteredResources() as $resource) {
            if ($resource instanceof DirectoryResource) {
                $checkers[] = new DirectoryStateChecker($resource);
            } else {
                $checkers[] = new FileStateChecker($resource);
            }
        }

        return $checkers;
    }
}
