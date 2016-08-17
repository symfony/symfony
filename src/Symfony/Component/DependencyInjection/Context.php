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

use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;
use Symfony\Component\DependencyInjection\Exception\ContextElementNotFoundException;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class Context
{
    private $elements;
    private $locked = false;

    /**
     * Creates a new context allowing to share elements during the container building phase.
     *
     * Context elements is an array where the values are the elements to share
     * and the keys are strings allowing to retrieve an element.
     *
     * For instance:
     *
     * new Context(array('kernel' => new BootingKernel($kernel)));
     *
     * @param array $elements An array of elements indexed by a string.
     */
    public function __construct(array $elements = array())
    {
        $this->elements = $elements;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has($key)
    {
        return isset($this->elements[$key]);
    }

    /**
     * @param string $key
     *
     * @return mixed
     *
     * @throws ContextElementNotFoundException When no element exists for this key.
     */
    public function get($key)
    {
        if (!$this->has($key)) {
            throw new ContextElementNotFoundException($key);
        }

        return $this->elements[$key];
    }

    /**
     * @param string $key
     * @param mixed  $element
     *
     * @throws BadMethodCallException When trying to set an element on a locked context.
     */
    public function set($key, $element)
    {
        if ($this->isLocked()) {
            throw new BadMethodCallException(sprintf('Setting element "%s" on a locked context is not allowed.', $key));
        }

        $this->elements[$key] = $element;
    }

    /**
     * Locks the context making it immutable.
     */
    public function lock()
    {
        $this->locked = true;
    }

    /**
     * @return bool
     */
    public function isLocked()
    {
        return $this->locked;
    }

    /**
     * Merges elements found in the given context into the current one.
     *
     * @param Context $context
     *
     * @throws BadMethodCallException When trying to merge into a locked context.
     */
    public function merge(Context $context)
    {
        if ($this->isLocked()) {
            throw new BadMethodCallException('Cannot merge on a locked context.');
        }

        foreach ($context->elements as $key => $element) {
            $this->set($key, $element);
        }
    }
}
