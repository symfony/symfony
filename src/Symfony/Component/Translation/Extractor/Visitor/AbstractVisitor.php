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
use Symfony\Component\Translation\MessageCatalogue;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
abstract class AbstractVisitor
{
    private const MAX_STRING_VALUES = 256;

    private MessageCatalogue $catalogue;
    private \SplFileInfo $file;
    private string $messagePrefix;

    public function initialize(MessageCatalogue $catalogue, \SplFileInfo $file, string $messagePrefix): void
    {
        $this->catalogue = $catalogue;
        $this->file = $file;
        $this->messagePrefix = $messagePrefix;
    }

    protected function addMessageToCatalogue(string $message, ?string $domain, int $line): void
    {
        $domain ??= 'messages';
        $this->catalogue->set($message, $this->messagePrefix.$message, $domain);
        $metadata = $this->catalogue->getMetadata($message, $domain) ?? [];
        $normalizedFilename = preg_replace('{[\\\\/]+}', '/', $this->file);
        $metadata['sources'][] = $normalizedFilename.':'.$line;
        $this->catalogue->setMetadata($message, $metadata, $domain);
    }

    protected function getStringArguments(Node\Expr\CallLike|Node\Attribute|Node\Expr\New_ $node, int|string $index, bool $indexIsRegex = false): array
    {
        if (\is_string($index)) {
            return $this->getStringNamedArguments($node, $index, $indexIsRegex);
        }

        $args = $node instanceof Node\Expr\CallLike ? $node->getRawArgs() : $node->args;

        if (!($arg = $args[$index] ?? null) instanceof Node\Arg) {
            return [];
        }

        return array_values(array_unique($this->getStringValues($arg->value)));
    }

    protected function hasNodeNamedArguments(Node\Expr\CallLike|Node\Attribute|Node\Expr\New_ $node): bool
    {
        $args = $node instanceof Node\Expr\CallLike ? $node->getRawArgs() : $node->args;

        foreach ($args as $arg) {
            if ($arg instanceof Node\Arg && null !== $arg->name) {
                return true;
            }
        }

        return false;
    }

    protected function nodeFirstNamedArgumentIndex(Node\Expr\CallLike|Node\Attribute|Node\Expr\New_ $node): int
    {
        $args = $node instanceof Node\Expr\CallLike ? $node->getRawArgs() : $node->args;

        foreach ($args as $i => $arg) {
            if ($arg instanceof Node\Arg && null !== $arg->name) {
                return $i;
            }
        }

        return \PHP_INT_MAX;
    }

    private function getStringNamedArguments(Node\Expr\CallLike|Node\Attribute $node, ?string $argumentName = null, bool $isArgumentNamePattern = false): array
    {
        $args = $node instanceof Node\Expr\CallLike ? $node->getArgs() : $node->args;
        $argumentValues = [];

        foreach ($args as $arg) {
            if (!$isArgumentNamePattern && $arg->name?->toString() === $argumentName) {
                $argumentValues[] = $this->getStringValues($arg->value);
            } elseif ($isArgumentNamePattern && preg_match($argumentName, $arg->name?->toString() ?? '') > 0) {
                $argumentValues[] = $this->getStringValues($arg->value);
            }
        }

        return array_values(array_unique(array_filter(array_merge(...$argumentValues))));
    }

    /**
     * @return list<string> All the string values the node can resolve to
     */
    private function getStringValues(Node $node): array
    {
        if ($node instanceof Node\Scalar\String_) {
            return [$node->value];
        }

        if ($node instanceof Node\Expr\BinaryOp\Concat) {
            $values = [];
            foreach ($this->getStringValues($node->left) as $left) {
                foreach ($this->getStringValues($node->right) as $right) {
                    if (self::MAX_STRING_VALUES <= \count($values)) {
                        return [];
                    }

                    $values[] = $left.$right;
                }
            }

            return $values;
        }

        if ($node instanceof Node\Expr\Ternary) {
            // each branch is a message on its own, so keep the resolvable ones even when the other is dynamic
            return [
                ...$this->getStringValues($node->if ?? $node->cond),
                ...$this->getStringValues($node->else),
            ];
        }

        if ($node instanceof Node\Expr\BinaryOp\Coalesce) {
            return [
                ...$this->getStringValues($node->left),
                ...$this->getStringValues($node->right),
            ];
        }

        if ($node instanceof Node\Expr\Assign) {
            return $this->getStringValues($node->expr);
        }

        if ($node instanceof Node\Expr\ClassConstFetch) {
            try {
                $reflection = new \ReflectionClass($node->class->toString());
                $constant = $reflection->getReflectionConstant($node->name->toString());
                if (false !== $constant && \is_string($constant->getValue())) {
                    return [$constant->getValue()];
                }
            } catch (\ReflectionException) {
            }
        }

        return [];
    }
}
