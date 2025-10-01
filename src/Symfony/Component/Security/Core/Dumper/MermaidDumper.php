<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Dumper;

use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * MermaidDumper dumps a Mermaid flowchart describing role hierarchy.
 *
 * @author Damien Fernandes <damien.fernandes24@gmail.com>
 */
class MermaidDumper
{
    /**
     * Dumps the role hierarchy as a Mermaid flowchart.
     *
     * @param RoleHierarchyInterface $roleHierarchy The role hierarchy to dump
     * @param MermaidDirection       $direction     The direction of the flowchart
     */
    public function dump(RoleHierarchyInterface $roleHierarchy, MermaidDirection $direction = MermaidDirection::TOP_TO_BOTTOM): string
    {
        $hierarchy = $this->extractHierarchy($roleHierarchy);

        if (!$hierarchy) {
            return "graph {$direction->value}\n    classDef default fill:#e1f5fe;";
        }

        $output = ["graph {$direction->value}"];
        $allRoles = $this->getAllRoles($hierarchy);

        foreach ($allRoles as $role) {
            $output[] = '    '.$this->normalizeRoleName($role);
        }

        foreach ($hierarchy as $parentRole => $childRoles) {
            foreach ($childRoles as $childRole) {
                $output[] = "    {$this->normalizeRoleName($parentRole)} --> {$this->normalizeRoleName($childRole)}";
            }
        }

        return implode("\n", array_filter($output));
    }

    private function extractHierarchy(RoleHierarchyInterface $roleHierarchy): array
    {
        if (!$roleHierarchy instanceof RoleHierarchy) {
            return [];
        }

        $reflection = new \ReflectionClass(RoleHierarchy::class);

        $hierarchyProperty = $reflection->getProperty('hierarchy');

        return $hierarchyProperty->getValue($roleHierarchy);
    }

    private function getAllRoles(array $hierarchy): array
    {
        $allRoles = [];

        foreach ($hierarchy as $parentRole => $childRoles) {
            $allRoles[] = $parentRole;
            foreach ($childRoles as $childRole) {
                $allRoles[] = $childRole;
            }
        }

        return array_unique($allRoles);
    }

    /**
     * Normalizes the role name by replacing non-alphanumeric characters with underscores.
     */
    private function normalizeRoleName(string $role): ?string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $role);
    }
}
