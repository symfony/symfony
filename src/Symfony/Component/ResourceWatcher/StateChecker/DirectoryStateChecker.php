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

use Symfony\Component\ResourceWatcher\Event\FilesystemEvent;
use Symfony\Component\Config\Resource\DirectoryResource;

/**
 * Recursive directory state checker.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class DirectoryStateChecker extends NewDirectoryStateChecker
{
    /**
     * Initializes checker.
     *
     * @param   DirectoryResource   $resource
     */
    public function __construct(DirectoryResource $resource, $eventsMask = FilesystemEvent::IN_ALL)
    {
        parent::__construct($resource, $eventsMask);

        foreach ($this->createDirectoryChildCheckers($resource) as $checker) {
            $this->childs[$checker->getResource()->getId()] = $checker;
        }
    }
}
