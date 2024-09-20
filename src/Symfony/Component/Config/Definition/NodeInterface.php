<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\ForbiddenOverwriteException;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * Common Interface among all nodes.
 *
 * In most cases, it is better to inherit from BaseNode instead of implementing
 * this interface yourself.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
interface NodeInterface
{
    /**
     * Returns the name of the node.
     */
    public function getName(): string;

    /**
     * Returns the path of the node.
     */
    public function getPath(): string;

    /**
     * Returns true when the node is required.
     */
    public function isRequired(): bool;

    /**
     * Returns true when the node has a default value.
     */
    public function hasDefaultValue(): bool;

    /**
     * Returns the default value of the node.
     *
     * @throws \RuntimeException if the node has no default value
     */
    public function getDefaultValue(): mixed;

    /**
     * Normalizes a value.
     *
     * @throws InvalidTypeException if the value type is invalid
     */
    public function normalize(mixed $value): mixed;

    /**
     * Merges two values together.
     *
     * @throws ForbiddenOverwriteException if the configuration path cannot be overwritten
     * @throws InvalidTypeException        if the value type is invalid
     */
    public function merge(mixed $leftSide, mixed $rightSide): mixed;

    /**
     * Finalizes a value.
     *
     * @throws InvalidTypeException          if the value type is invalid
     * @throws InvalidConfigurationException if the value is invalid configuration
     */
    public function finalize(mixed $value): mixed;
}
