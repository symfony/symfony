<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class Alias
{
    private const DEFAULT_DEPRECATION_TEMPLATE = 'The "%alias_id%" service alias is deprecated. You should stop using it, as it will be removed in the future.';

    private bool $public = false;
    private array $deprecation = [];

    public function __construct(
        private string $id,
        bool $public = false,
    ) {
        $this->public = $public;
    }

    /**
     * Checks if this DI Alias should be public or not.
     */
    public function isPublic(): bool
    {
        return $this->public;
    }

    /**
     * Sets if this Alias is public.
     *
     * @return $this
     */
    public function setPublic(bool $boolean): static
    {
        $this->public = $boolean;

        return $this;
    }

    /**
     * Whether this alias is private.
     */
    public function isPrivate(): bool
    {
        return !$this->public;
    }

    /**
     * Whether this alias is deprecated, that means it should not be referenced
     * anymore.
     *
     * @param string $package The name of the composer package that is triggering the deprecation
     * @param string $version The version of the package that introduced the deprecation
     * @param string $message The deprecation message to use
     *
     * @return $this
     *
     * @throws InvalidArgumentException when the message template is invalid
     */
    public function setDeprecated(string $package, string $version, string $message): static
    {
        if ('' !== $message) {
            if (preg_match('#[\r\n]|\*/#', $message)) {
                throw new InvalidArgumentException('Invalid characters found in deprecation template.');
            }

            if (!str_contains($message, '%alias_id%')) {
                throw new InvalidArgumentException('The deprecation template must contain the "%alias_id%" placeholder.');
            }
        }

        $this->deprecation = ['package' => $package, 'version' => $version, 'message' => $message ?: self::DEFAULT_DEPRECATION_TEMPLATE];

        return $this;
    }

    public function isDeprecated(): bool
    {
        return (bool) $this->deprecation;
    }

    /**
     * @param string $id Service id relying on this definition
     */
    public function getDeprecation(string $id): array
    {
        return [
            'package' => $this->deprecation['package'],
            'version' => $this->deprecation['version'],
            'message' => str_replace('%alias_id%', $id, $this->deprecation['message']),
        ];
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function __serialize(): array
    {
        $data = [];
        foreach ((array) $this as $k => $v) {
            if (!$v) {
                continue;
            }
            if (false !== $i = strrpos($k, "\0")) {
                $k = substr($k, 1 + $i);
            }
            $data[$k] = $v;
        }

        return $data;
    }
}
