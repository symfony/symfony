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
 * Annotates method calls with a closure returning the expressions returned by the called methods,
 * so that translation messages returned by methods can be extracted.
 *
 * The called methods are found using reflection, when the class of the object they are called on is known:
 * "$this", "self", "static", typed parameters and properties, instantiations, enum cases and methods declaring
 * their return type. Resolving them is deferred until the returned values are actually needed.
 *
 * Variables must have been annotated by the VariableResolver beforehand.
 *
 * @internal
 */
final class CallResolver extends NodeVisitorAbstract
{
    public const RETURNED_VALUES = 'returnedValues';

    private const RETURNED_EXPRESSIONS = 'returnedExpressions';

    private NodeFinder $finder;

    /**
     * @var list<string|null>
     */
    private array $classes;

    /**
     * @var list<list<Node\Expr>>
     */
    private array $returns;

    /**
     * @param \Closure(string): Node[] $parse Returns the annotated nodes of a file
     */
    public function __construct(
        private \Closure $parse,
    ) {
        $this->finder = new NodeFinder();
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->classes = [];
        $this->returns = [];

        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\ClassLike) {
            $this->classes[] = $node->namespacedName?->toString();
        } elseif ($node instanceof Node\FunctionLike) {
            $this->returns[] = [];
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\ClassLike) {
            array_pop($this->classes);
        } elseif ($node instanceof Node\FunctionLike) {
            $node->setAttribute(self::RETURNED_EXPRESSIONS, array_pop($this->returns));
        } elseif ($node instanceof Node\Stmt\Return_ && null !== $node->expr && $this->returns) {
            $this->returns[array_key_last($this->returns)][] = $node->expr;
        } elseif ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall || $node instanceof Node\Expr\StaticCall) {
            $class = end($this->classes) ?: null;
            $node->setAttribute(self::RETURNED_VALUES, fn (): array => $this->getReturnedValues($node, $class));
        }

        return null;
    }

    /**
     * @param string|null $class The class the call is made from
     *
     * @return list<Node\Expr>
     */
    private function getReturnedValues(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall|Node\Expr\StaticCall $node, ?string $class): array
    {
        $values = [];

        foreach ($this->getMethods($node, $class) as $method) {
            if (false === $file = $method->getFileName()) {
                continue;
            }

            $methodNode = $this->finder->findFirst(($this->parse)($file), static fn (Node $child) => $child instanceof Node\Stmt\ClassMethod
                && $child->getEndLine() === $method->getEndLine()
                && $child->name->toLowerString() === strtolower($method->name)
            );

            $values[] = $methodNode?->getAttribute(self::RETURNED_EXPRESSIONS) ?? [];
        }

        return array_merge(...$values);
    }

    /**
     * @return array<string, \ReflectionMethod> The called methods, indexed by the class they are called on
     */
    private function getMethods(Node\Expr\MethodCall|Node\Expr\NullsafeMethodCall|Node\Expr\StaticCall $node, ?string $class): array
    {
        if (!$node->name instanceof Node\Identifier) {
            return [];
        }

        $methods = [];

        foreach ($this->getClassNames($node instanceof Node\Expr\StaticCall ? $node->class : $node->var, $class) as $className) {
            try {
                $methods[$className] = new \ReflectionMethod($className, $node->name->toString());
            } catch (\ReflectionException) {
            }
        }

        return $methods;
    }

    /**
     * @return list<string> The classes of the objects the node may evaluate to
     */
    private function getClassNames(Node $node, ?string $class): array
    {
        if ($node instanceof Node\Name) {
            return match ($node->toLowerString()) {
                'self', 'static' => null !== $class ? [$class] : [],
                'parent' => null !== $class && ($parent = get_parent_class($class)) ? [$parent] : [],
                default => [$node->toString()],
            };
        }

        if ($node instanceof Node\NullableType) {
            return $this->getClassNames($node->type, $class);
        }

        if ($node instanceof Node\UnionType) {
            $typeClassNames = [];
            foreach ($node->types as $type) {
                $typeClassNames[] = $this->getClassNames($type, $class);
            }

            return array_merge(...$typeClassNames);
        }

        if ($node instanceof Node\Param) {
            return null !== $node->type ? $this->getClassNames($node->type, $class) : [];
        }

        if ($node instanceof Node\Expr\Variable) {
            if ('this' === $node->name) {
                return null !== $class ? [$class] : [];
            }

            $valueClassNames = [];
            foreach ($node->getAttribute(VariableResolver::ASSIGNED_VALUES, []) as $value) {
                $valueClassNames[] = $this->getClassNames($value, $class);
            }

            return array_merge(...$valueClassNames);
        }

        if ($node instanceof Node\Expr\New_) {
            return $node->class instanceof Node\Name ? $this->getClassNames($node->class, $class) : [];
        }

        $classNames = [];

        if ($node instanceof Node\Expr\ClassConstFetch && $node->name instanceof Node\Identifier) {
            // enum cases
            foreach ($this->getClassNames($node->class, $class) as $className) {
                try {
                    $value = (new \ReflectionClassConstant($className, $node->name->toString()))->getValue();
                } catch (\ReflectionException) {
                    continue;
                }

                if (\is_object($value)) {
                    $classNames[] = $value::class;
                }
            }
        } elseif ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\NullsafeMethodCall || $node instanceof Node\Expr\StaticCall) {
            foreach ($this->getMethods($node, $class) as $className => $method) {
                $classNames[] = $this->getTypeNames($method->getReturnType(), $method->class, $className);
            }

            $classNames = array_merge(...$classNames);
        } elseif (($node instanceof Node\Expr\PropertyFetch || $node instanceof Node\Expr\NullsafePropertyFetch) && $node->name instanceof Node\Identifier) {
            foreach ($this->getClassNames($node->var, $class) as $className) {
                try {
                    $property = new \ReflectionProperty($className, $node->name->toString());
                } catch (\ReflectionException) {
                    continue;
                }

                $classNames[] = $this->getTypeNames($property->getType(), $property->class, $className);
            }

            $classNames = array_merge(...$classNames);
        }

        return $classNames;
    }

    /**
     * @param string $self   The class declaring the type
     * @param string $static The class the type is read from
     *
     * @return list<string>
     */
    private function getTypeNames(?\ReflectionType $type, string $self, string $static): array
    {
        if ($type instanceof \ReflectionUnionType) {
            return array_merge(...array_map(fn (\ReflectionType $type) => $this->getTypeNames($type, $self, $static), $type->getTypes()));
        }

        if (!$type instanceof \ReflectionNamedType) {
            return [];
        }

        return match ($type->getName()) {
            'self' => [$self],
            'static' => [$static],
            default => $type->isBuiltin() ? [] : [$type->getName()],
        };
    }
}
