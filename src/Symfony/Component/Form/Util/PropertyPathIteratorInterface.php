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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
interface PropertyPathIteratorInterface extends \Iterator, \SeekableIterator
{
    /**
     * Returns whether the current element in the property path is an array
     * index.
     *
     * @return Boolean
     */
    public function isIndex();

    /**
     * Returns whether the current element in the property path is a property
     * name.
     *
     * @return Boolean
     */
    public function isProperty();
}
