<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Traits;

use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\CacheItem;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait ArrayTrait
{
    use LoggerAwareTrait;

    private $storeSerialized;
    private $values = array();
    private $expiries = array();

    /**
     * Returns all cached values, with cache miss as null.
     *
     * @return array
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        CacheItem::validateKey($key);

        return isset($this->expiries[$key]) && ($this->expiries[$key] >= time() || !$this->deleteItem($key));
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->values = $this->expiries = array();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        CacheItem::validateKey($key);

        unset($this->values[$key], $this->expiries[$key]);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function reset()
    {
        $this->clear();
    }

    private function generateItems(array $keys, $now, $f)
    {
        foreach ($keys as $i => $key) {
            try {
                if (!$isHit = isset($this->expiries[$key]) && ($this->expiries[$key] >= $now || !$this->deleteItem($key))) {
                    $this->values[$key] = $value = null;
                } elseif (!$this->storeSerialized) {
                    $value = $this->values[$key];
                } elseif ('b:0;' === $value = $this->values[$key]) {
                    $value = false;
                } elseif (false === $value = unserialize($value)) {
                    $this->values[$key] = $value = null;
                    $isHit = false;
                }
            } catch (\Exception $e) {
                CacheItem::log($this->logger, 'Failed to unserialize key "{key}"', array('key' => $key, 'exception' => $e));
                $this->values[$key] = $value = null;
                $isHit = false;
            }
            unset($keys[$i]);

            yield $key => $f($key, $value, $isHit);
        }

        foreach ($keys as $key) {
            yield $key => $f($key, null, false);
        }
    }
}
