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
 * You can wrap the iterator into a {@link \RecursiveIterator} in order to
 * enter any child form that inherits its parent's data and iterate the children
 * of that form as well.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @deprecated since version 2.3, to be removed in 3.0.
 *             Use {@link InheritDataAwareIterator} instead.
 */
class VirtualFormAwareIterator extends \IteratorIterator implements \RecursiveIterator
{
    public function __construct(\Traversable $iterator)
    {
        /*
         * Prevent to trigger deprecation notice when already using the
         * InheritDataAwareIterator class that extends this deprecated one.
         * The {@link Symfony\Component\Form\Util\InheritDataAwareIterator::__construct} method
         * forces this argument to false.
         */
        if (__CLASS__ === get_class($this)) {
            @trigger_error('The '.__CLASS__.' class is deprecated since version 2.3 and will be removed in 3.0. Use the Symfony\Component\Form\Util\InheritDataAwareIterator class instead.', E_USER_DEPRECATED);
        }

        parent::__construct($iterator);
    }

    /**
     * {@inheritdoc}
     */
    public function getChildren()
    {
        return new static($this->current());
    }

    /**
     *{@inheritdoc}
     */
    public function hasChildren()
    {
        return (bool) $this->current()->getConfig()->getInheritData();
    }
}
