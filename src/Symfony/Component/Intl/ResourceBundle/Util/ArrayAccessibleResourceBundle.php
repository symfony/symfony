<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Intl\ResourceBundle\Util;

use Symfony\Component\Intl\Exception\BadMethodCallException;

/**
 * Work-around for a bug in PHP's \ResourceBundle implementation.
 *
 * More information can be found on https://bugs.php.net/bug.php?id=64356.
 * This class can be removed once that bug is fixed.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @since v2.3.0
 */
class ArrayAccessibleResourceBundle implements \ArrayAccess, \IteratorAggregate, \Countable
{
    private $bundleImpl;

    /**
     * @since v2.3.0
     */
    public function __construct(\ResourceBundle $bundleImpl)
    {
        $this->bundleImpl = $bundleImpl;
    }

    /**
     * @since v2.3.0
     */
    public function get($offset, $fallback = null)
    {
        $value = $this->bundleImpl->get($offset, $fallback);

        return $value instanceof \ResourceBundle ? new static($value) : $value;
    }

    /**
     * @since v2.3.0
     */
    public function offsetExists($offset)
    {
        return null !== $this->bundleImpl[$offset];
    }

    /**
     * @since v2.3.0
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * @since v2.3.0
     */
    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException('Resource bundles cannot be modified.');
    }

    /**
     * @since v2.3.0
     */
    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('Resource bundles cannot be modified.');
    }

    /**
     * @since v2.3.0
     */
    public function getIterator()
    {
        return $this->bundleImpl;
    }

    /**
     * @since v2.3.0
     */
    public function count()
    {
        return $this->bundleImpl->count();
    }

    /**
     * @since v2.3.0
     */
    public function getErrorCode()
    {
        return $this->bundleImpl->getErrorCode();
    }

    /**
     * @since v2.3.0
     */
    public function getErrorMessage()
    {
        return $this->bundleImpl->getErrorMessage();
    }
}
