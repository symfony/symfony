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
     *
     * @return string
     */
    public function getName();

    /**
     * Returns the path of the node.
     *
     * @return string
     */
    public function getPath();

    /**
     * Returns true when the node is required.
     *
     * @return bool
     */
    public function isRequired();

    /**
     * Returns true when the node has a default value.
     *
     * @return bool
     */
    public function hasDefaultValue();

    /**
     * Returns the default value of the node.
     *
     * @return mixed
     *
     * @throws \RuntimeException if the node has no default value
     */
    public function getDefaultValue();

    /**
     * Normalizes a value.
     *
     * @param mixed $value The value to normalize
     *
     * @return mixed
     *
     * @throws InvalidTypeException if the value type is invalid
     */
    public function normalize($value);

    /**
     * Merges two values together.
     *
     * @param mixed $leftSide
     * @param mixed $rightSide
     *
     * @return mixed
     *
     * @throws ForbiddenOverwriteException if the configuration path cannot be overwritten
     * @throws InvalidTypeException        if the value type is invalid
     */
    public function merge($leftSide, $rightSide);

    /**
     * Finalizes a value.
     *
     * @param mixed $value The value to finalize
     *
     * @return mixed
     *
     * @throws InvalidTypeException          if the value type is invalid
     * @throws InvalidConfigurationException if the value is invalid configuration
     */
    public function finalize($value);
}
