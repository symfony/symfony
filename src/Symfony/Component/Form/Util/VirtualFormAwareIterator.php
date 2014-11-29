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
 * @deprecated Deprecated since version 2.3, to be removed in 3.0. Use
 *             {@link InheritDataAwareIterator} instead.
 */
class VirtualFormAwareIterator extends \IteratorIterator implements \RecursiveIterator
{

    public function __construct(\Traversable $iterator)
    {
        parent::__construct($iterator);

        if ('Symfony\Component\Form\Util\VirtualFormAwareIterator' === get_class()) {
            trigger_error('class VirtualFormAwareIterator is deprecated since version 2.7 and will be removed in 3.0. Use InheritDataAwareIterator instead.', E_USER_DEPRECATED);
        }
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
