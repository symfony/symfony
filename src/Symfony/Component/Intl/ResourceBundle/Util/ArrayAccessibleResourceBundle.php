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
 * @internal
 */
class ArrayAccessibleResourceBundle implements \ArrayAccess, \IteratorAggregate, \Countable
{
    private $bundleImpl;

    public function __construct(\ResourceBundle $bundleImpl)
    {
        $this->bundleImpl = $bundleImpl;
    }

    public function get($offset)
    {
        $value = $this->bundleImpl->get($offset);

        return $value instanceof \ResourceBundle ? new static($value) : $value;
    }

    public function offsetExists($offset)
    {
        return null !== $this->bundleImpl->get($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException('Resource bundles cannot be modified.');
    }

    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('Resource bundles cannot be modified.');
    }

    public function getIterator()
    {
        return $this->bundleImpl;
    }

    public function count()
    {
        return $this->bundleImpl->count();
    }

    public function getErrorCode()
    {
        return $this->bundleImpl->getErrorCode();
    }

    public function getErrorMessage()
    {
        return $this->bundleImpl->getErrorMessage();
    }
}
