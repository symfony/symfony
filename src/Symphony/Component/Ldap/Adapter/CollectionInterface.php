<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Ldap\Adapter;

use Symphony\Component\Ldap\Entry;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
interface CollectionInterface extends \Countable, \IteratorAggregate, \ArrayAccess
{
    /**
     * @return Entry[]
     */
    public function toArray();
}
