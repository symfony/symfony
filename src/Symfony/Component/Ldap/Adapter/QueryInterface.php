<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
namespace Symfony\Component\Ldap\Adapter;

use Symfony\Component\Ldap\Entry;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 */
interface QueryInterface
{
    const DEREF_NEVER = 0;
    const DEREF_SEARCHING = 1;
    const DEREF_FINDING = 2;
    const DEREF_ALWAYS = 3;

    /**
     * Executes a query and returns the list of Ldap entries.
     *
     * @return CollectionInterface|Entry[]
     */
    public function execute();
}
