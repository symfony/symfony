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
 * Iterator that traverses an array of forms.
 *
 * Contrary to \ArrayIterator, this iterator recognizes changes in the original
 * array during iteration.
 *
 * You can wrap the iterator into a {@link \RecursiveIterator} in order to
 * enter any child form that inherits its parent's data and iterate the children
 * of that form as well.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
 *             {@link InheritDataAwareIterator} instead.
 */
class VirtualFormAwareIterator implements \RecursiveIterator
{
    /**
     * @var \Symfony\Component\Form\FormInterface[]
     */
    private $forms;

    /**
     * Creates a new iterator.
     *
     * @param \Symfony\Component\Form\FormInterface[] $forms An array of forms
     */
    public function __construct(array &$forms)
    {
        // Uncomment this as soon as the deprecation note should be shown
        // trigger_error('VirtualFormAwareIterator is deprecated since version 2.3 and will be removed in 3.0. Use InheritDataAwareIterator instead.', E_USER_DEPRECATED);

        $this->forms = &$forms;
    }

    /**
     *{@inheritdoc}
     */
    public function current()
    {
        return current($this->forms);
    }

    /**
     *{@inheritdoc}
     */
    public function next()
    {
        next($this->forms);
    }

    /**
     *{@inheritdoc}
     */
    public function key()
    {
        return key($this->forms);
    }

    /**
     *{@inheritdoc}
     */
    public function valid()
    {
        return null !== key($this->forms);
    }

    /**
     *{@inheritdoc}
     */
    public function rewind()
    {
        reset($this->forms);
    }

    /**
     *{@inheritdoc}
     */
    public function getChildren()
    {
        return new static($this->current()->all());
    }

    /**
     *{@inheritdoc}
     */
    public function hasChildren()
    {
        return (bool) $this->current()->getConfig()->getInheritData();
    }
}
