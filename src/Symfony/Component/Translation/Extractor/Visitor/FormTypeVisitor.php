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

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 *
 * Code mostly comes from https://github.com/php-translation/extractor/blob/master/src/Visitor/Php/Symfony/
 */
final class FormTypeVisitor extends AbstractVisitor implements NodeVisitor
{
    use FormTrait;

    public function __construct(
        // to be deleted
        private readonly array $formTypeClassNames = [],
    ) {
    }

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
}
