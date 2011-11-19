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
            $this->childs[(string) $checker->getResource()] = $checker;
        }
    }

    /**
     * Check tracked resource for changes.
     *
     * @return  array
     */
    public function checkChanges()
    {
        $changeset = parent::checkChanges();
        if ((isset($changeset[0]) && $changeset[0]['event'] === Event::DELETED) || $this->isDeleted()) {
            return $changeset;
        }

        foreach ($this->childs as $path => $checker) {
            foreach ($checker->checkChanges() as $change) {
                $changeset[] = $change;
            }
        }

        foreach ($this->createDirectoryChildCheckers($this->getResource()) as $checker) {
            if (!isset($this->childs[(string) $checker->getResource()])) {
                $this->childs[(string) $checker->getResource()] = $checker;
                $changeset[] = array(
                    'event' => Event::CREATED, 'resource' => $checker->getResource()
                );
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
        foreach ($resource->getFilteredChildResources() as $resource) {
            if ($resource instanceof DirectoryResource) {
                $checkers[] = new DirectoryStateChecker($resource);
            } else {
                $checkers[] = new FileStateChecker($resource);
            }
        }

        return $checkers;
    }
}
