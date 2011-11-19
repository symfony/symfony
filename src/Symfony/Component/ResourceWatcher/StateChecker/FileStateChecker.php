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
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\FileResource;

/**
 * File state checker.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class FileStateChecker extends ResourceStateChecker
{
    /**
     * Initializes checker.
     *
     * @param   FileResource   $resource
     */
    public function __construct(FileResource $resource)
    {
        parent::__construct($resource);
    }
}
