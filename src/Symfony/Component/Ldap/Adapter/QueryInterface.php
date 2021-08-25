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

use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Exception\NotBoundException;

/**
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Bob van de Vijver <bobvandevijver@hotmail.com>
 */
interface QueryInterface
{
    public const DEREF_NEVER = 0x00;
    public const DEREF_SEARCHING = 0x01;
    public const DEREF_FINDING = 0x02;
    public const DEREF_ALWAYS = 0x03;

    public const SCOPE_BASE = 'base';
    public const SCOPE_ONE = 'one';
    public const SCOPE_SUB = 'sub';

    /**
     * Executes a query and returns the list of Ldap entries.
     *
     * @throws NotBoundException
     * @throws LdapException
     */
    public function execute(): CollectionInterface;
}
