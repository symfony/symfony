<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ResourceWatcher\Event;

use Symfony\Component\ResourceWatcher\Resource\TrackedResource;
use Symfony\Component\ResourceWatcher\Exception\InvalidArgumentException;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\EventDispatcher\Event;

/**
 * Resource change event.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class FilesystemEvent extends Event
{
    const IN_CREATE = 1;
    const IN_MODIFY = 2;
    const IN_DELETE = 4;
    const IN_ALL    = 7;

    private $tracked;
    private $resource;
    private $type;

    protected static $types = array(
        1 => 'create',
        2 => 'modify',
        4 => 'delete',
    );

    /**
     * Initializes resource event.
     *
     * @param   TrackedResource   $tracked      resource, that being tracked
     * @param   ResourceInterface $resource     resource instance
     * @param   integer           $type         event type bit
     */
    public function __construct(TrackedResource $tracked, ResourceInterface $resource, $type)
    {
        if (!isset(self::$types[$type])) {
            throw new InvalidArgumentException('Wrong event type providen');
        }

        $this->tracked  = $tracked;
        $this->resource = $resource;
        $this->type     = $type;
    }

    /**
     * Returns resource, that being tracked while event occured.
     *
     * @return  integer
     */
    public function getTrackedResource()
    {
        return $this->tracked;
    }

    /**
     * Returns changed resource.
     *
     * @return  ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * Returns true is resource, that fired event is file.
     *
     * @return  Boolean
     */
    public function isFileChange()
    {
        return $this->resource instanceof FileResource;
    }

    /**
     * Returns true is resource, that fired event is directory.
     *
     * @return  Boolean
     */
    public function isDirectoryChange()
    {
        return $this->resource instanceof DirectoryResource;
    }

    /**
     * Returns event type.
     *
     * @return  integer
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns event type string representation.
     *
     * @return  string
     */
    public function getTypeString()
    {
        return self::$types[$this->getType()];
    }
}
