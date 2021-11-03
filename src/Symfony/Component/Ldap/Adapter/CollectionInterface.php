<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Adapter;

use Symfony\Component\Ldap\Entry;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 *
 * @extends \ArrayAccess<int, Entry>
 * @extends \IteratorAggregate<int, Entry>
 */
interface CollectionInterface extends \Countable, \IteratorAggregate, \ArrayAccess
{
    /**
     * @return list<Entry>
     */
    public function toArray();
}
