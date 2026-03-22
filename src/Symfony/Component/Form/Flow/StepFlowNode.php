<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Flow;

use Symfony\Component\Form\Exception\LogicException;

/**
 * Represents a node in the step flow tree (forest graph).
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 */
class StepFlowNode
{
    /** @var list<self> */
    private array $children = [];
    private ?self $previousSibling = null;
    private ?self $nextSibling = null;

    private function __construct(
        private readonly string $name,
        private readonly ?\Closure $skip = null,
        private readonly bool $group = false,
        private readonly ?self $parent = null,
    ) {
    }

    /**
     * @param list<string> $steps
     *
     * @return list<self>
     */
    public static function fromArray(array $steps): array
    {
        $nodes = [];
        $previous = null;

        foreach ($steps as $name) {
            $node = new self($name);

            if ($previous) {
                $previous->nextSibling = $node;
                $node->previousSibling = $previous;
            }

            $nodes[] = $node;
            $previous = $node;
        }

        return $nodes;
    }

    /**
     * Builds a forest from step configurations.
     *
     * @param array<string, StepFlowConfigInterface> $steps Ordered step configs
     *
     * @return list<self>
     */
    public static function fromConfig(array $steps, ?self $parent = null): array
    {
        $nodes = [];
        $previous = null;

        foreach ($steps as $name => $step) {
            $node = new self($name, $step->getSkip(), $step->isGroup(), $parent);

            if ($previous) {
                $previous->nextSibling = $node;
                $node->previousSibling = $previous;
            }

            if ($children = $step->getSteps()) {
                $node->children = self::fromConfig($children, $node);
            } elseif ($node->group) {
                throw new LogicException(\sprintf('Step "%s" is marked as group but has no child steps.', $name));
            }

            $nodes[] = $node;
            $previous = $node;
        }

        return $nodes;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * @return list<self>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getNextSibling(): ?self
    {
        return $this->nextSibling;
    }

    public function getPreviousSibling(): ?self
    {
        return $this->previousSibling;
    }

    public function isGroup(): bool
    {
        return $this->group;
    }

    public function getSkip(): ?\Closure
    {
        return $this->skip;
    }

    public function isGroupOrSkipped(mixed $data): bool
    {
        if ($this->group) {
            return true;
        }

        if ($this->skip) {
            return ($this->skip)($data);
        }

        // Check ancestors: if a parent is skipped via skip func, children are too
        $ancestor = $this->parent;
        while ($ancestor) {
            if ($ancestor->skip && ($ancestor->skip)($data)) {
                return true;
            }
            $ancestor = $ancestor->parent;
        }

        return false;
    }

    /**
     * Returns the next node in DFS pre-order traversal.
     *
     * If this node has children, returns the first child.
     * Otherwise returns the next sibling, or walks up to
     * find an ancestor with a next sibling.
     */
    public function getNextInTraversal(): ?self
    {
        if ($this->children) {
            return $this->children[0];
        }

        if ($this->nextSibling) {
            return $this->nextSibling;
        }

        $ancestor = $this->parent;
        while ($ancestor) {
            if ($ancestor->nextSibling) {
                return $ancestor->nextSibling;
            }
            $ancestor = $ancestor->parent;
        }

        return null;
    }

    /**
     * Returns the previous node in DFS pre-order traversal.
     *
     * If this node has a previous sibling, returns that sibling's
     * deepest last descendant. Otherwise returns the parent.
     */
    public function getPreviousInTraversal(): ?self
    {
        if ($this->previousSibling) {
            return $this->previousSibling->getDeepestLastDescendant();
        }

        return $this->parent;
    }

    /**
     * Returns the deepest last descendant of this node.
     *
     * Recursively follows the last child until a leaf node is reached.
     * Returns self if this node has no children.
     */
    private function getDeepestLastDescendant(): self
    {
        if (!$this->children) {
            return $this;
        }

        return $this->children[\count($this->children) - 1]->getDeepestLastDescendant();
    }
}
