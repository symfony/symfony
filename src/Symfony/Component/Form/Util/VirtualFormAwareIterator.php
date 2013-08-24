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
 * enter any virtual child form and iterate the children of that virtual form.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class VirtualFormAwareIterator extends \ArrayIterator implements \RecursiveIterator
{
    /**
     *{@inheritdoc}
     */
    public function getChildren()
    {
        return new self($this->current()->all());
    }

    /**
     *{@inheritdoc}
     */
    public function hasChildren()
    {
        return (bool) $this->current()->getConfig()->getVirtual();
    }
}
