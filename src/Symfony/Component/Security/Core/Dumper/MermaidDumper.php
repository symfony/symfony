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
            return '';
        }

        $output = ["graph {$direction->value}"];
        $allRoles = $this->getAllRoles($hierarchy);

        foreach ($allRoles as $role) {
            $output[] = '    '.$this->dumpNode($role);
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
     * Node IDs only allow a limited set of characters, so roles whose name
     * is changed by the normalization (e.g. "ROLE_ADMIN-TEST") need an explicit label.
     */
    private function dumpNode(string $role): string
    {
        $id = $this->normalizeRoleName($role);

        if ($id === $role) {
            return $id;
        }

        // "#" goes first so that the entities inserted after it are not escaped again
        return \sprintf('%s["%s"]', $id, str_replace(['#', '"', '<', '>'], ['#35;', '#quot;', '#lt;', '#gt;'], $role));
    }

    /**
     * Normalizes the role name by replacing non-alphanumeric characters with underscores.
     */
    private function normalizeRoleName(string $role): ?string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $role);
    }
}
