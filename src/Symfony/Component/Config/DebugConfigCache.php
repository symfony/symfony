<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\Util\CacheFileUtils;
use Symfony\Component\Config\Resource\ResourceValidatorInterface;

/**
 * A ConfigCache that keeps track of the Resources used to build the cache.
 * A set of ResourceValidators is used to check whether any of the resource
 * has been changed and if so, the cache is flushed.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DebugConfigCache extends ProductionConfigCache
{
    protected $resourceValidators = array();

    public function addResourceValidator(ResourceValidatorInterface $validator)
    {
        $this->resourceValidators[] = $validator;
    }

    public function setResourceValidators(array $validators)
    {
        $this->resourceValidators = $validators;
    }

    public function getResourceValidators()
    {
        return $this->resourceValidators;
    }

    protected function getMetaFile()
    {
        return $this->file.'.meta';
    }

    /**
     * Checks if the cache is still fresh.
     *
     * This method always returns true when debug is off and the
     * cache file exists.
     *
     * @return Boolean true if the cache is fresh, false otherwise
     */
    public function isFresh()
    {
        if (!parent::isFresh()) {
            return false;
        }

        if (!is_file($this->getMetaFile())) {
            return false;
        }

        $resources = unserialize(file_get_contents($this->getMetaFile()));
        foreach ($resources as $resource) {
            foreach ($this->resourceValidators as $validator) {
                $check = $validator->isFresh($resource);
                if (true === $check) {
                    continue 2; // next resource
                } elseif (false === $check) {
                    return false; // this resource is stale
                }
            }

            // TBD: What if no validator knows how to check the resource?
            return false;
        }

        return true;
    }

    /**
     * Writes cache.
     *
     * @param string              $content  The content to write in the cache
     * @param ResourceInterface[] $metadata An array of ResourceInterface instances
     *
     * @throws \RuntimeException When cache file can't be wrote
     */
    public function write($content, array $metadata = null)
    {
        CacheFileUtils::dumpInFile($this->file, $content);

        if (null !== $metadata) {
            CacheFileUtils::dumpInFile($this->getMetaFile(), serialize(array_unique($metadata, SORT_REGULAR)));
        }
    }
}
