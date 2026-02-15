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
use Symfony\Component\Cache\Exception\LogicException;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class CacheItem implements ItemInterface
{
    private const METADATA_EXPIRY_OFFSET = 1527506807;
    private const VALUE_WRAPPER = "\xA9";

    protected string $key;
    protected mixed $value = null;
    protected bool $isHit = false;
    protected float|int|null $expiry = null;
    protected array $metadata = [];
    protected array $newMetadata = [];
    protected ?CacheItemInterface $innerItem = null;
    protected ?string $poolHash = null;
    protected bool $isTaggable = false;

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->isHit;
    }

    /**
     * @return $this
     */
    public function set($value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return $this
     */
    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->expiry = null !== $expiration ? (float) $expiration->format('U.u') : null;

        return $this;
    }

    /**
     * @return $this
     */
    public function expiresAfter(mixed $time): static
    {
        if (null === $time) {
            $this->expiry = null;
        } elseif ($time instanceof \DateInterval) {
            $this->expiry = microtime(true) + \DateTimeImmutable::createFromFormat('U', 0)->add($time)->format('U.u');
        } elseif (\is_int($time)) {
            $this->expiry = $time + microtime(true);
        } else {
            throw new InvalidArgumentException(\sprintf('Expiration date must be an integer, a DateInterval or null, "%s" given.', get_debug_type($time)));
        }

        return $this;
    }

    public function tag(mixed $tags): static
    {
        if (!$this->isTaggable) {
            throw new LogicException(\sprintf('Cache item "%s" comes from a non tag-aware pool: you cannot tag it.', $this->key));
        }
        if (!\is_array($tags) && !$tags instanceof \Traversable) { // don't use is_iterable(), it's slow
            $tags = [$tags];
        }
        foreach ($tags as $tag) {
            if (!\is_string($tag) && !$tag instanceof \Stringable) {
                throw new InvalidArgumentException(\sprintf('Cache tag must be string or object that implements __toString(), "%s" given.', get_debug_type($tag)));
            }
            $tag = (string) $tag;
            if (isset($this->newMetadata[self::METADATA_TAGS][$tag])) {
                continue;
            }
            if ('' === $tag) {
                throw new InvalidArgumentException('Cache tag length must be greater than zero.');
            }
            if (false !== strpbrk($tag, self::RESERVED_CHARACTERS)) {
                throw new InvalidArgumentException(\sprintf('Cache tag "%s" contains reserved characters "%s".', $tag, self::RESERVED_CHARACTERS));
            }
            $this->newMetadata[self::METADATA_TAGS][$tag] = $tag;
        }

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Validates a cache key according to PSR-6.
     *
     * @param mixed $key The key to validate
     *
     * @throws InvalidArgumentException When $key is not valid
     */
    public static function validateKey($key): string
    {
        if (!\is_string($key)) {
            throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given.', get_debug_type($key)));
        }
        if ('' === $key) {
            throw new InvalidArgumentException('Cache key length must be greater than zero.');
        }
        if (false !== strpbrk($key, self::RESERVED_CHARACTERS)) {
            throw new InvalidArgumentException(\sprintf('Cache key "%s" contains reserved characters "%s".', $key, self::RESERVED_CHARACTERS));
        }

        return $key;
    }

    /**
     * Internal logging helper.
     *
     * @internal
     */
    public static function log(?LoggerInterface $logger, string $message, array $context = []): void
    {
        if ($logger) {
            $logger->warning($message, $context);
        } else {
            $replace = [];
            foreach ($context as $k => $v) {
                if (\is_scalar($v)) {
                    $replace['{'.$k.'}'] = $v;
                }
            }
            @trigger_error(strtr($message, $replace), \E_USER_WARNING);
        }
    }

    private function pack(): mixed
    {
        if (!$m = $this->newMetadata) {
            return $this->value;
        }
        $valueWrapper = self::VALUE_WRAPPER;

        return new $valueWrapper($this->value, $m + ['expiry' => $this->expiry]);
    }

    private function unpack(): bool
    {
        $v = $this->value;
        $valueWrapper = self::VALUE_WRAPPER;

        if ($v instanceof $valueWrapper) {
            $this->value = $v->value;
            $this->metadata = $v->metadata;

            return true;
        }

        if (!\is_array($v) || 1 !== \count($v) || 10 !== \strlen($k = (string) array_key_first($v)) || "\x9D" !== $k[0] || "\0" !== $k[5] || "\x5F" !== $k[9]) {
            return false;
        }

        // BC with pools populated before v6.1
        $this->value = $v[$k];
        $this->metadata = unpack('Vexpiry/Nctime', substr($k, 1, -1));
        $this->metadata['expiry'] += self::METADATA_EXPIRY_OFFSET;

        return true;
    }
}

// @php-cs-fixer-ignore protected_to_private Friend-level scope access relies on protected properties
