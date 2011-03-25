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

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\Resource\FileResource;

/**
 * Recursive iterator resource state checker.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class RecursiveIteratorStateChecker implements StateCheckerInterface
{
    private $resource;
    private $timestamp;
    private $deleted     = false;
    private $subcheckers = array();

    /**
     * Initializes checker.
     *
     * @param   ResourceInterface   $resource
     */
    public function __construct(ResourceInterface $resource)
    {
        $this->resource  = $resource;
        $this->timestamp = filemtime($this->getResource()->getResource());

        if ($resource instanceof DirectoryResource) {
            $subresources = $this->readResourcesFromDirectory($resource);

            foreach ($subresources as $subresource) {
                $checker = new RecursiveIteratorStateChecker($subresource);
                $this->subcheckers[(string) $subresource] = $checker;
            }
        }
    }

    /**
     * Returns tracked resource.
     *
     * @return  ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Is tracked resource still exists.
     *
     * @return  Boolean
     */
    public function isResourceExists()
    {
        return !$this->deleted;
    }

    /**
     * Check tracked resource for changes.
     *
     * @return  array
     */
    public function checkChanges()
    {
        if ($this->deleted) {
            return array();
        }

        $changeset = array();
        if ($state = $this->getResourceChangeStateSince($this->getResource(), $this->timestamp + 1)) {
            if ('deleted' === $state) {
                $this->deleted = true;
            } elseif ('modified' === $state) {
                $this->timestamp = filemtime($this->getResource()->getResource());
            }

            $changeset = array($state => array($this->getResource()));

            if ('deleted' === $state) {
                return $changeset;
            }
        }

        if ($this->getResource() instanceof DirectoryResource) {
            foreach ($this->subcheckers as $path => $checker) {
                $changeset = array_merge_recursive($changeset, $checker->checkChanges());
            }

            $subresources = $this->readResourcesFromDirectory($this->getResource());
            foreach ($subresources as $subresource) {
                if (!isset($this->subcheckers[(string) $subresource])) {
                    $checker = new RecursiveIteratorStateChecker($subresource);
                    $this->subcheckers[(string) $subresource] = $checker;
                    $changeset = array_merge_recursive($changeset, array('created' => array($subresource)));
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
    private function readResourcesFromDirectory(DirectoryResource $resource)
    {
        $iterator   = new \DirectoryIterator($resource->getResource());
        $resources  = array();

        foreach ($iterator as $info) {
            if ($info->isDot()) {
                continue;
            }

            if ($info->isDir()) {
                $resources[] = new DirectoryResource($info->getPathname());
            } else {
                $resources[] = new FileResource($info->getPathname());
            }
        }

        return $resources;
    }

    /**
     * Checks resource change state since provided timestamp.
     *
     * @param   ResourceInterface   $resource
     * @param   integer             $timestamp
     *
     * @return  string|Boolean      deleted|modified|false
     */
    private function getResourceChangeStateSince(ResourceInterface $resource, $timestamp)
    {
        if (!file_exists($resource->getResource())) {
            return 'deleted';
        } elseif (!$resource->isFresh($timestamp)) {
            return 'modified';
        }

        return false;
    }
}
