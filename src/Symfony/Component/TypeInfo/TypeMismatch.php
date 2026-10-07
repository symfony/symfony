<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo;

/**
 * Tells where and why a value is not accepted by a type.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class TypeMismatch
{
    /**
     * @param string      $path         The property path of the mismatching value, e.g. "[data][0].name", or an empty string for the value itself
     * @param Type        $expectedType The type expected at this path, "never" when the key or property is not expected at all
     * @param string|null $actualType   The type of the mismatching value as returned by get_debug_type(), or null when the key or property is missing
     */
    public function __construct(
        public readonly string $path,
        public readonly Type $expectedType,
        public readonly ?string $actualType,
    ) {
    }

    /**
     * @internal
     */
    public function withParentPath(string $path): self
    {
        $separator = '' === $this->path || '[' === $this->path[0] ? '' : '.';

        return new self($path.$separator.$this->path, $this->expectedType, $this->actualType);
    }
}
