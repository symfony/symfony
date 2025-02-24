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
use PhpParser\NodeVisitor;
use Symfony\Component\Form\AbstractType;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * Code mostly comes from https://github.com/php-translation/extractor/blob/master/src/Visitor/Php/Symfony/
 */
final class FormTypeVisitor extends AbstractVisitor implements NodeVisitor
{
    /**
     * Stores whether the current class is a form type across visits of all children nodes.
     */
    private bool $isFormType = false;

    public function beforeTraverse(array $nodes): ?Node
    {
        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        if (!$this->isFormType($node)) {
            return null;
        }

        // Visit all array expressions to look for options array (containing explicit labels)
        if ($node instanceof Node\Expr\Array_) {
            $this->visitArray($node);
        }

        // Visit all "add()" method calls to look for implicit labels
        if ($node instanceof Node\Expr\MethodCall) {
            $this->visitMethodCall($node);
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        return null;
    }

    public function afterTraverse(array $nodes): ?Node
    {
        return null;
    }

    private function visitMethodCall(Node\Expr\MethodCall $node): void
    {
        if ('add' !== $node->name->name) {
            return;
        }

        if (!$node->args[0]->value instanceof Node\Scalar\String_) {
            return;
        }

        if (\count($node->args) === 1) {
            $this->addMessageToCatalogue($this->getStringValue($node->args[0]->value), 'messages', $node->args[0]->value->getStartLine());
        }
    }

    private function visitArray(Node\Expr\Array_ $node): void
    {
        foreach ($node->items as $item) {
            if ($item->key instanceof Node\Scalar\String_ && 'label' === $item->key->value) {
                // If the label is a non-empty string, add it to the messages
                $stringValue = $this->getStringValue($item->value);
                if (null !== $stringValue && '' !== $stringValue) {
                    $this->addMessageToCatalogue($stringValue, 'messages', $item->getStartLine());
                }
            }
        }
    }

    private function isFormType(Node $node): bool
    {
        if ($node instanceof Node\Stmt\Class_) {
            if ($node->extends !== null) {
                if ($node->extends->isFullyQualified()) {
                    if ($node->extends->name === AbstractType::class) {
                        $this->isFormType = true;
                    }
                }
            }
        }

        return $this->isFormType;
    }
}
