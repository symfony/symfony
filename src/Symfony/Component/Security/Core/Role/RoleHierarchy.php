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
 * RoleHierarchy defines a role hierarchy.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoleHierarchy implements RoleHierarchyInterface
{
    /**
     * Map role placeholders with their regex pattern.
     *
     * @var array<string,string>
     */
    private array $rolePlaceholdersPatterns;

    /** @var array<string, list<string>> */
    protected array $map;

    private array $hierarchy;

    /**
     * @param array<string, list<string>> $hierarchy
     */
    public function __construct(array $hierarchy)
    {
        $this->hierarchy = $hierarchy;

        $this->buildRoleMap();
    }

    public function getReachableRoleNames(array $roles): array
    {
        return array_values(array_unique($this->resolveReachableRoleNames($roles)));
    }

    protected function buildRoleMap(): void
    {
        $this->map = [];
        $this->rolePlaceholdersPatterns = [];

        foreach ($this->hierarchy as $main => $roles) {
            $this->map[$main] = $roles;
            $visited = [];
            $additionalRoles = $roles;
            while ($role = array_shift($additionalRoles)) {
                if (!isset($this->hierarchy[$role])) {
                    continue;
                }

                $visited[] = $role;

                foreach ($this->hierarchy[$role] as $roleToAdd) {
                    $this->map[$main][] = $roleToAdd;
                }

                foreach (array_diff($this->hierarchy[$role], $visited) as $additionalRole) {
                    $additionalRoles[] = $additionalRole;
                }
            }

            $this->map[$main] = array_unique($this->map[$main]);

            if (str_contains($main, '*')) {
                $this->rolePlaceholdersPatterns[$main] = sprintf('/%s/', strtr($main, ['*' => '[^\*]+']));
            }
        }
    }

    private function resolveReachableRoleNames(array $roles, array &$visitedPlaceholders = []): array
    {
        $reachableRoles = $roles;

        foreach ($roles as $role) {
            if (!isset($this->map[$role])) {
                continue;
            }

            foreach ($this->map[$role] as $r) {
                $reachableRoles[] = $r;
            }
        }

        $placeholderRoles = array_diff($this->getMatchingPlaceholders($reachableRoles), $visitedPlaceholders);
        if (!empty($placeholderRoles)) {
            array_push($visitedPlaceholders, ...$placeholderRoles);
            $resolvedPlaceholderRoles = $this->resolveReachableRoleNames($placeholderRoles, $visitedPlaceholders);
            foreach (array_diff($resolvedPlaceholderRoles, $placeholderRoles) as $r) {
                $reachableRoles[] = $r;
            }
        }

        return $reachableRoles;
    }

    private function getMatchingPlaceholders(array $roles): array
    {
        $resolved = [];

        foreach ($this->rolePlaceholdersPatterns as $placeholder => $pattern) {
            if (!\in_array($placeholder, $resolved) && \count(preg_grep($pattern, $roles) ?? null)) {
                $resolved[] = $placeholder;
            }
        }

        return $resolved;
    }
}
