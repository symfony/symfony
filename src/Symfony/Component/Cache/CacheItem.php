<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Cache;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CacheItem implements TaggedCacheItemInterface
{
    /**
     * @internal
     */
    const CAST_PREFIX = "\0Symfony\Component\Cache\CacheItem\0";

    private $key;
    private $value;
    private $isHit;
    private $expiry;
    private $defaultLifetime;
    private $tags = array();

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
    public function get()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function isHit()
    {
        return $this->isHit;
    }

    /**
     * {@inheritdoc}
     */
    public function set($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAt($expiration)
    {
        if (null === $expiration) {
            $this->expiry = $this->defaultLifetime > 0 ? time() + $this->defaultLifetime : null;
        } elseif ($expiration instanceof \DateTimeInterface) {
            $this->expiry = (int) $expiration->format('U');
        } else {
            throw new InvalidArgumentException(sprintf('Expiration date must implement DateTimeInterface or be null, "%s" given', is_object($expiration) ? get_class($expiration) : gettype($expiration)));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function expiresAfter($time)
    {
        if (null === $time) {
            $this->expiry = $this->defaultLifetime > 0 ? time() + $this->defaultLifetime : null;
        } elseif ($time instanceof \DateInterval) {
            $this->expiry = (int) \DateTime::createFromFormat('U', time())->add($time)->format('U');
        } elseif (is_int($time)) {
            $this->expiry = $time + time();
        } else {
            throw new InvalidArgumentException(sprintf('Expiration date must be an integer, a DateInterval or null, "%s" given', is_object($time) ? get_class($time) : gettype($time)));
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function tag($tags)
    {
        $this->tags += self::normalizeTags($tags);

        return $this;
    }

    /**
     * Normalizes cache tags.
     *
     * @param string|string[] $tags The tags to validate.
     *
     * @throws InvalidArgumentException When $tags is not valid.
     */
    public static function normalizeTags($tags)
    {
        if (!is_array($tags)) {
            $tags = array($tags);
        }
        $normalizedTags = array();

        foreach ($tags as $tag) {
            if (!is_string($tag)) {
                throw new InvalidArgumentException(sprintf('Cache tag must be string, "%s" given', is_object($tag) ? get_class($tag) : gettype($tag)));
            }
            if (!isset($tag[0])) {
                throw new InvalidArgumentException('Cache tag length must be greater than zero');
            }
            if (isset($tag[strcspn($tag, '{}()\@:')])) {
                throw new InvalidArgumentException(sprintf('Cache tag "%s" contains reserved characters {}()*\@:', $tag));
            }
            if (false === $r = strrpos($tag, '/')) {
                $tag = '/'.$tag;
                $normalizedTags[$tag] = $tag;
                continue;
            }
            if (!isset($tag[$r + 1])) {
                throw new InvalidArgumentException(sprintf('Cache tag "%s" ends with a slash', $tag));
            }
            if (false !== strpos($tag, '//')) {
                throw new InvalidArgumentException(sprintf('Cache tag "%s" contains double slashes', $tag));
            }
            if ('/' !== $tag[0]) {
                $tag = '/'.$tag;
            }
            $normalizedTags[$tag] = $tag;
        }

        return $normalizedTags;
    }

    /**
     * Validates a cache key according to PSR-6.
     *
     * @param string $key The key to validate.
     *
     * @throws InvalidArgumentException When $key is not valid.
     */
    public static function validateKey($key)
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException(sprintf('Cache key must be string, "%s" given', is_object($key) ? get_class($key) : gettype($key)));
        }
        if (!isset($key[0])) {
            throw new InvalidArgumentException('Cache key length must be greater than zero');
        }
        if (isset($key[strcspn($key, '{}()/\@:')])) {
            throw new InvalidArgumentException(sprintf('Cache key "%s" contains reserved characters {}()/\@:', $key));
        }
    }

    /**
     * Internal logging helper.
     *
     * @internal
     */
    public static function log(LoggerInterface $logger = null, $message, $context = array())
    {
        if ($logger) {
            $logger->warning($message, $context);
        } else {
            $replace = array();
            foreach ($context as $k => $v) {
                if (is_scalar($v)) {
                    $replace['{'.$k.'}'] = $v;
                }
            }
            @trigger_error(strtr($message, $replace), E_USER_WARNING);
        }
    }
}
