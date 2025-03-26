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

final readonly class AssignDefaultRoles implements RoleFetcherInterface
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        private array $roles,
    ) {
    }

    /**
     * @return string[]
     */
    public function fetchRoles(Entry $entry): array
    {
        return $this->roles;
    }
}
