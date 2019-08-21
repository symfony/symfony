<?php

namespace Symfony\Component\Cache\Tests\Fixtures;

use Doctrine\Common\Cache\CacheProvider;

class ArrayCache extends CacheProvider
{
    private $data = [];

    protected function doFetch($id)
    {
        return $this->doContains($id) ? $this->data[$id][0] : false;
    }

    protected function doContains($id): bool
    {
        if (!isset($this->data[$id])) {
            return false;
        }

        $expiry = $this->data[$id][1];

        return !$expiry || microtime(true) < $expiry || !$this->doDelete($id);
    }

    protected function doSave($id, $data, $lifeTime = 0): bool
    {
        $this->data[$id] = [$data, $lifeTime ? microtime(true) + $lifeTime : false];

        return true;
    }

    protected function doDelete($id): bool
    {
        unset($this->data[$id]);

        return true;
    }

    protected function doFlush(): bool
    {
        $this->data = [];

        return true;
    }

    protected function doGetStats(): ?array
    {
        return null;
    }
}
