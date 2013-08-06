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
 *
 * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
 *             {@link InheritDataAwareIterator} instead.
 */
class VirtualFormAwareIterator extends \ArrayIterator implements \RecursiveIterator
{
    /**
     * Creates a new iterator.
     *
     * @param \Symfony\Component\Form\FormInterface[] $forms An array
     */
    public function __construct(array $forms)
    {
        // Uncomment this as soon as the deprecation note should be shown
        // trigger_error('VirtualFormAwareIterator is deprecated since version 2.3 and will be removed in 3.0. Use InheritDataAwareIterator instead.', E_USER_DEPRECATED);

        parent::__construct($forms);
    }

    public function getChildren()
    {
        return new static($this->current()->all());
    }

    public function hasChildren()
    {
        return $this->current()->getConfig()->getInheritData();
    }
}
