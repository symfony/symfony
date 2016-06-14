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

use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Exception\InvalidArgumentException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CacheItem implements CacheItemInterface
{
    protected $key;
    protected $value;
    protected $isHit;
    protected $expiry;
    protected $defaultLifetime;
    protected $innerItem;
    protected $poolHash;

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
