<?php

namespace Symfony\Component\Validator\Mapping\Cache;

use Symfony\Component\Validator\Mapping\ClassMetadata;

class ApcCache implements CacheInterface
{
    public function has($class)
    {
        apc_delete($this->computeCacheKey($class));
        apc_fetch($this->computeCacheKey($class), $exists);

        return $exists;
    }

    public function read($class)
    {
        if (!$this->has($class)) {
            // TODO exception
        }

        return apc_fetch($this->computeCacheKey($class));
    }

    public function write(ClassMetadata $metadata)
    {
        apc_store($this->computeCacheKey($metadata->getClassName()), $metadata);
    }

    protected function computeCacheKey($class)
    {
        return 'Symfony\Components\Validator:'.$class;
    }
}
