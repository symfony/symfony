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

use Symfony\Component\DependencyInjection\Exception\ContextElementNotFoundException;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
final class Context
{
    private $elements;

    private function __construct(array $elements = array())
    {
        $this->elements = $elements;
    }

    /**
     * Creates a new context allowing to share elements during the container building phase.
     *
     * Context elements is an array where the values are the elements to share
     * and the keys are strings allowing to retrieve an element.
     *
     * For instance:
     *
     * Context::create(array('kernel' => new BootingKernel($kernel)));
     *
     * You can optionally pass an existing Context instance. Thus, original elements are reused,
     * but new context elements replace any existing key.
     *
     * @param array        $elements An array of elements indexed by a string.
     * @param Context|null $previous An optional Context instance used as base.
     *
     * @return Context
     */
    public static function create(array $elements = array(), Context $previous = null)
    {
        return new self($previous ? array_replace($previous->elements, $elements) : $elements);
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
}
