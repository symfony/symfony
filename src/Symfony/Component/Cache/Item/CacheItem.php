<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache\Item;
use DateTime;
use DateTimeZone;

/**
 * Implementation of the cache object/container
 *
 * @author  Florin Patan <florinpatan@gmail.com>
 */
class CacheItem implements CacheItemInterface
{

    /**
     * Cache key
     *
     * @var string
     */
    private $key;

    /**
     * Cache value
     *
     * @var mixed
     */
    private $value;

    /**
     * TTL of the object in cache
     *
     * @var int
     */
    private $ttl;

    /**
     * Metadata information for the cached object
     *
     * @var array
     */
    private $metadata = array();

    /**
     * Create our cache container object
     *
     * @param string    $key
     * @param mixed     $value
     * @param int       $ttl
     */
    public function __construct($key, $value, $ttl)
    {
        $this->setKey($key)->setValue($value)->setTtl($ttl);
    }

    /**
     * {@inheritdoc}
     */
    public function setKey($cacheKey)
    {
        $this->key = $cacheKey;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * {@inheritdoc}
     */
    public function setValue($cacheValue)
    {
        $this->value = $cacheValue;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function setTtl($ttl)
    {
        $this->ttl = $ttl;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function getRemainingTtl()
    {
        $saveTime = $this->getMetadata('__tos');

        if ($saveTime == '') {
            return $this->ttl;
        }

        $now = new DateTime('now', new DateTimeZone('UTC'));

        return $saveTime + $this->ttl - $now->getTimestamp();
    }


    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        return $this->getMetadata('__ns');
    }

    /**
     * {@inheritdoc}
     */
    public function setNamespace($namespace)
    {
        $this->setMetadata('__ns', $namespace);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getTags()
    {
        return $this->getMetadata('__tags') == '' ? array() : $this->getMetadata('__tags');
    }

    /**
     * {@inheritdoc}
     */
    public function setTags(array $tags)
    {
        $this->setMetadata('__tags', $tags);

        return $this;
    }


    /**
     * {@inheritdoc}
     */
    public function setMetadata($key, $value)
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasMetadata($key = null)
    {
        if ($key == null) {
            return !empty($this->metadata);
        }

        return array_key_exists($key, $this->metadata);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        if ($key == null) {
            return $this->metadata;
        }

        return array_key_exists($key, $this->metadata) ? $this->metadata[$key] : '';
    }

    public function __sleep()
    {
        $timeOfSave = new DateTime('now', new DateTimeZone('UTC'));
        $this->setMetadata('__tos', $timeOfSave->getTimestamp());

        return array('key', 'value', 'ttl', 'metadata');
    }

}
