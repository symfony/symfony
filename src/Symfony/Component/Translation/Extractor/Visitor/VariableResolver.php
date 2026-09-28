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
 * Annotates variables with the expressions assigned to them earlier in the same scope.
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

    /**
     * @var list<int> The number of branches the traversal is in, for each scope
     */
    private array $branchDepths;

    public function __construct()
    {
        $this->finder = new NodeFinder();
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->scopes = [[]];
        $this->branchDepths = [0];

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
            $this->branchDepths[] = 0;
        } elseif (self::isBranch($node)) {
            ++$this->branchDepths[array_key_last($this->branchDepths)];
        } elseif ($node instanceof Node\Expr\Variable && \is_string($node->name)) {
            $node->setAttribute(self::ASSIGNED_VALUES, end($this->scopes)[$node->name] ?? []);
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Node\FunctionLike) {
            array_pop($this->scopes);
            array_pop($this->branchDepths);

            return null;
        }

        if (self::isBranch($node)) {
            --$this->branchDepths[array_key_last($this->branchDepths)];
        }

        if (!($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignOp) || !$node->var instanceof Node\Expr\Variable || !\is_string($name = $node->var->name)) {
            return null;
        }

        $scope = &$this->scopes[array_key_last($this->scopes)];

        if (!$node instanceof Node\Expr\Assign) {
            $scope[$name] = $this->compoundAssign($scope[$name] ?? [], $node);
        } elseif (!end($this->branchDepths) || $this->finder->findFirst($node->expr, static fn (Node $child) => $child instanceof Node\Expr\Variable && $name === $child->name)) {
            // an assignment that always runs replaces the previous values; like ".=", one reusing the variable still reaches them through it
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
     * @param list<Node\Expr|Node\Param> $values The expressions the variable may hold before the assignment; $node->var is annotated with them, so it can be reused to build new expressions
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

    /**
     * Tells whether the children of the node may not run.
     */
    private static function isBranch(Node $node): bool
    {
        return $node instanceof Node\Stmt\If_
            || $node instanceof Node\Stmt\Switch_
            || $node instanceof Node\Stmt\TryCatch
            || $node instanceof Node\Stmt\For_
            || $node instanceof Node\Stmt\Foreach_
            || $node instanceof Node\Stmt\While_
            || $node instanceof Node\Stmt\Do_
            || $node instanceof Node\Expr\Ternary
            || $node instanceof Node\Expr\Match_
            || $node instanceof Node\Expr\BinaryOp\Coalesce
            || $node instanceof Node\Expr\AssignOp\Coalesce
            || $node instanceof Node\Expr\BinaryOp\BooleanAnd
            || $node instanceof Node\Expr\BinaryOp\BooleanOr
            || $node instanceof Node\Expr\BinaryOp\LogicalAnd
            || $node instanceof Node\Expr\BinaryOp\LogicalOr
            || $node instanceof Node\Expr\NullsafeMethodCall;
    }
}
