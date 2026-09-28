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
     * Ids that break the diagram: the Mermaid keywords, and the properties every JavaScript object has, since the layout engine stores nodes in plain objects keyed by id.
     */
    private const RESERVED_IDS = [
        'call', 'class', 'classDef', 'click', 'end', 'flowchart', 'graph', 'href', 'interpolate', 'linkStyle', 'style', 'subgraph', '_blank', '_parent', '_self', '_top',
        '__defineGetter__', '__defineSetter__', '__lookupGetter__', '__lookupSetter__', '__proto__', 'constructor', 'hasOwnProperty', 'isPrototypeOf', 'propertyIsEnumerable', 'toLocaleString', 'toString', 'valueOf',
    ];

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
        $ids = $this->getNodeIds($allRoles);

        foreach ($allRoles as $role) {
            $output[] = '    '.$this->dumpNode($role, $ids[$role]);
        }

        foreach ($hierarchy as $parentRole => $childRoles) {
            foreach ($childRoles as $childRole) {
                $output[] = "    {$ids[$parentRole]} --> {$ids[$childRole]}";
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
     * Gives each role a node id that no other role uses.
     *
     * When several roles normalize to the same id, the role whose name is that id keeps it, or else the first of them by name.
     * The other roles get the first free numbered suffix in the order of their names, so the ids do not depend on the order of the hierarchy.
     * A reserved id is kept by no role.
     *
     * @param string[] $roles
     *
     * @return array<string, string>
     */
    private function getNodeIds(array $roles): array
    {
        $rolesById = [];
        foreach ($roles as $role) {
            $rolesById[$this->normalizeRoleName($role)][] = (string) $role;
        }

        $ids = [];
        foreach ($rolesById as $id => $sameIdRoles) {
            $id = (string) $id;
            sort($sameIdRoles, \SORT_STRING);

            if (!\in_array($id, self::RESERVED_IDS, true)) {
                $ids[\in_array($id, $sameIdRoles, true) ? $id : $sameIdRoles[0]] = $id;
            }

            $i = 0;
            foreach ($sameIdRoles as $role) {
                if (isset($ids[$role])) {
                    continue;
                }

                do {
                    $suffixedId = $id.'_'.++$i;
                } while (isset($rolesById[$suffixedId]));

                $ids[$role] = $suffixedId;
            }
        }

        return $ids;
    }

    /**
     * A role whose node id is not its name (e.g. "ROLE_ADMIN-TEST") needs an explicit label.
     */
    private function dumpNode(string $role, string $id): string
    {
        if ($id === $role) {
            return $id;
        }

        // "#" goes first so that the entity inserted after it is not escaped again
        return \sprintf('%s["%s"]', $id, str_replace(['#', '"'], ['#35;', '#quot;'], $role));
    }

    /**
     * Normalizes the role name by replacing non-alphanumeric characters with underscores.
     */
    private function normalizeRoleName(string $role): ?string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $role);
    }
}
