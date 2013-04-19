<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

/**
 * Iterator that returns only forms from a form tree that do not inherit their
 * parent data.
 *
 * If the iterator encounters a form that inherits its parent data, it enters
 * the form and traverses its children as well.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class InheritDataAwareIterator extends VirtualFormAwareIterator
{
    /**
     * Creates a new iterator.
     *
     * @param \Symfony\Component\Form\FormInterface[] $forms An array
     */
    public function __construct(array $forms)
    {
        // Skip the deprecation error
        \ArrayIterator::__construct($forms);
    }
}
