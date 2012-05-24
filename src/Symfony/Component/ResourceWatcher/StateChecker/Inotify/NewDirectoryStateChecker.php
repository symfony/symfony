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
use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;

/**
 * Directory state checker. Sets for itself and children a flag that indicates newness.
 *
 * @author Yaroslav Kiliba <om.dattaya@gmail.com>
 */
class NewDirectoryStateChecker extends DirectoryStateChecker
{
    /**
     * @var bool|null
     */
    protected $isNew = true;

    /**
     * Initializes checker.
     *
     * @param CheckerBag $bag
     * @param DirectoryResource $resource
     * @param int $eventsMask
     */
    public function __construct(CheckerBag $bag, DirectoryResource $resource, $eventsMask = FilesystemEvent::IN_ALL)
    {
        $this->setEvent(IN_CREATE);
        parent::__construct($bag, $resource, $eventsMask);
    }

    /**
     * {@inheritdoc}
     */
    protected function createChildCheckers()
    {
        foreach ($this->getResource()->getFilteredResources() as $resource) {
            $basename = basename((string) $resource);
            if ($resource instanceof DirectoryResource) {
                $this->createNewDirectoryChecker($basename, $resource);
            } else {
                $this->files[$basename] = $resource;
                $this->fileEvents[$basename] = 'new';
            }
        }
    }
}
