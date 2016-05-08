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
 */
interface QueryInterface
{
    const DEREF_NEVER = 0x00;
    const DEREF_SEARCHING = 0x01;
    const DEREF_FINDING = 0x02;
    const DEREF_ALWAYS = 0x03;

    /**
     * Executes a query and returns the list of Ldap entries.
     *
     * @return CollectionInterface|Entry[]
     */
    public function execute();
}
