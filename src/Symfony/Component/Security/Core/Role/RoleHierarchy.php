<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Role;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoleHierarchy implements RoleHierarchyInterface
{
    /** @var array<string, array<string, string>> */
    protected array $map;

    /**
     * The regex matching the roles each placeholder of the hierarchy stands for, keyed by placeholder.
     *
     * @var array<string, string>
     */
    protected private(set) array $rolePlaceholdersPatterns = [];

    /**
     * @param array<string, list<string>> $hierarchy
     */
    public function __construct(
        private array $hierarchy,
    ) {
        $this->buildRoleMap();
    }

    public function getReachableRoleNames(array $roles): array
    {
        $reachableRoles = [];

        foreach ($roles as $role) {
            $reachableRoles[$role] = $role;

            if (!isset($this->map[$role])) {
                continue;
            }

            foreach ($this->map[$role] as $r) {
                $reachableRoles[$r] = $r;
            }
        }

        if ($this->rolePlaceholdersPatterns) {
            $this->addRolesMatchingPlaceholders($reachableRoles);
        }

        return array_values($reachableRoles);
    }

    /**
     * @param string[] $roles
     *
     * @return list<string>
     */
    public function getParentRoleNames(array $roles): array
    {
        $parentRoles = [];

        foreach ($roles as $role) {
            $parentRoles[$role] = $role;

            foreach ($this->map as $parent => $children) {
                // a placeholder is a pattern, not a role anyone can hold
                if (isset($children[$role]) && !isset($this->rolePlaceholdersPatterns[$parent])) {
                    $parentRoles[$parent] = $parent;
                }
            }
        }

        return array_values($parentRoles);
    }

    protected function buildRoleMap(): void
    {
        $this->map = [];
        $this->rolePlaceholdersPatterns = [];
        foreach ($this->hierarchy as $main => $roles) {
            $map = [];
            $visited = [];
            $additionalRoles = $roles;
            while (null !== $role = key($additionalRoles)) {
                $role = $additionalRoles[$role];
                $map[$role] = $role;

                if (!isset($this->hierarchy[$role])) {
                    next($additionalRoles);
                    continue;
                }

                $visited[] = $role;

                foreach ($this->hierarchy[$role] as $roleToAdd) {
                    $map[$roleToAdd] = $roleToAdd;
                }

                foreach (array_diff($this->hierarchy[$role], $visited) as $additionalRole) {
                    $additionalRoles[] = $additionalRole;
                }

                next($additionalRoles);
            }

            $this->map[$main] = $map;

            if (str_contains($main, '*') && null !== $pattern = $this->getPlaceholderPattern($main)) {
                $this->rolePlaceholdersPatterns[$main] = $pattern;
            }
        }
    }

    /**
     * Returns the placeholders of the hierarchy matched by at least one of the given roles.
     *
     * @param list<string> $roles
     *
     * @return list<string>
     */
    protected function getMatchingPlaceholders(array $roles): array
    {
        $matching = [];

        foreach ($this->rolePlaceholdersPatterns as $placeholder => $pattern) {
            if (preg_grep($pattern, $roles)) {
                $matching[] = $placeholder;
            }
        }

        return $matching;
    }

    /**
     * Adds the roles the matched placeholders stand for, and the ones those roles reach in turn.
     *
     * The placeholders themselves are never added: they are patterns, not roles.
     *
     * @param array<string, string> $reachableRoles
     */
    private function addRolesMatchingPlaceholders(array &$reachableRoles): void
    {
        $visited = [];

        while ($placeholders = array_diff($this->getMatchingPlaceholders(array_keys($reachableRoles)), $visited)) {
            foreach ($placeholders as $placeholder) {
                $visited[] = $placeholder;

                foreach ($this->map[$placeholder] ?? [] as $r) {
                    $reachableRoles[$r] = $r;
                }
            }
        }
    }

    /**
     * Builds the regex matching the roles the given placeholder stands for.
     *
     * A wildcard is a "*" preceded by "_" and followed by "_" or the end of the role.
     *
     * @return string|null The pattern, or null when the role carries no valid wildcard
     */
    private function getPlaceholderPattern(string $role): ?string
    {
        $pattern = preg_replace('/(?<=_)\\\\\*(?=_|$)/', '.*', preg_quote($role, '/'), -1, $count);

        return $count ? '/^'.$pattern.'$/' : null;
    }
}
