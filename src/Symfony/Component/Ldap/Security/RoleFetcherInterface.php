<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Ldap\Security;

use Symfony\Component\Ldap\Entry;

/**
 * Fetches LDAP roles for a given entry.
 */
interface RoleFetcherInterface
{
    /**
     * @return string[] The list of roles
     */
    public function fetchRoles(Entry $entry): array;
}
