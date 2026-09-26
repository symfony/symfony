<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Extractor\Visitor;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeVisitorAbstract;

/**
 * Annotates variables with the expressions assigned to them earlier in the same scope,
 * so that translation messages passed as variables can be extracted.
 *
 * Parameters are annotated with their Param node, which gives access to their type.
 *
 * @internal
 */
final class VariableResolver extends NodeVisitorAbstract
{
    public const ASSIGNED_VALUES = 'assignedValues';

    private NodeFinder $finder;

    /**
     * @var list<array<string, list<Node\Expr|Node\Param>>>
     */
    private array $scopes;

    public function __construct()
    {
        $this->finder = new NodeFinder();
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->scopes = [[]];

        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Node\FunctionLike) {
            $scope = $this->getInheritedValues($node, end($this->scopes));

            foreach ($node->getParams() as $param) {
                if ($param->var instanceof Node\Expr\Variable && \is_string($param->var->name)) {
                    $scope[$param->var->name] = [$param];
                }
            }

            $this->scopes[] = $scope;
        } elseif ($node instanceof Node\Expr\Variable && \is_string($node->name)) {
            $node->setAttribute(self::ASSIGNED_VALUES, end($this->scopes)[$node->name] ?? []);
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Node\FunctionLike) {
            array_pop($this->scopes);

            return null;
        }

        if (!($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignOp) || !$node->var instanceof Node\Expr\Variable || !\is_string($name = $node->var->name)) {
            return null;
        }

        $scope = &$this->scopes[array_key_last($this->scopes)];

        if (!$node instanceof Node\Expr\Assign) {
            $scope[$name] = $this->compoundAssign($scope[$name] ?? [], $node);
        } elseif ($this->finder->findFirst($node->expr, static fn (Node $child) => $child instanceof Node\Expr\Variable && $name === $child->name)) {
            // like ".=", an assignment reusing the variable replaces its previous values, which are still reachable through it
            $scope[$name] = [$node->expr];
        } else {
            // the variable may still hold its previous values when the assignment is conditional
            $scope[$name] = [...$scope[$name] ?? [], $node->expr];
        }

        return null;
    }

    /**
     * @param array<string, list<Node\Expr|Node\Param>> $parentScope
     *
     * @return array<string, list<Node\Expr|Node\Param>>
     */
    private function getInheritedValues(Node\FunctionLike $node, array $parentScope): array
    {
        if ($node instanceof Node\Expr\ArrowFunction) {
            // arrow functions capture the whole parent scope by value
            return $parentScope;
        }

        $scope = [];

        if ($node instanceof Node\Expr\Closure) {
            // closures only capture the variables listed in their "use" clause
            foreach ($node->uses as $use) {
                if (\is_string($name = $use->var->name) && isset($parentScope[$name])) {
                    $scope[$name] = $parentScope[$name];
                }
            }
        }

        return $scope;
    }

    /**
     * Computes the values a variable may hold after a compound assignment like "$message .= '.suffix'".
     *
     * @param list<Node\Expr|Node\Param> $values The expressions the variable may hold before the assignment;
     *                                           $node->var is annotated with them, so it can be reused to build new expressions
     *
     * @return list<Node\Expr|Node\Param> The expressions the variable may hold after the assignment
     */
    private function compoundAssign(array $values, Node\Expr\AssignOp $node): array
    {
        return match (true) {
            // the previous values are replaced, so that partial messages like "app." are not extracted
            $node instanceof Node\Expr\AssignOp\Concat => [new Node\Expr\BinaryOp\Concat($node->var, $node->expr)],
            $node instanceof Node\Expr\AssignOp\Coalesce => [...$values, $node->expr],
            // other operators like "+=" or "|=" do not produce strings
            default => [],
        };
    }
}
