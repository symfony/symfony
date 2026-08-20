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
use Symfony\Component\Translation\TranslatableMessage;

/**
 * @author Mathieu Santostefano <msantostefano@protonmail.com>
 */
final class TranslatableMessageVisitor extends AbstractVisitor implements NodeVisitor
{
    public function beforeTraverse(array $nodes): ?Node
    {
        return null;
    }

    public function enterNode(Node $node): ?Node
    {
        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        if (!$node instanceof Node\Expr\New_) {
            return null;
        }

        if (!($className = $node->class) instanceof Node\Name) {
            return null;
        }

        // the name resolver gives a fully qualified name; templates without a "use"
        // statement resolve to the global namespace, which is accepted as well
        if (!\in_array($className->toString(), [TranslatableMessage::class, 'TranslatableMessage'], true)) {
            return null;
        }

        $firstNamedArgumentIndex = $this->nodeFirstNamedArgumentIndex($node);

        if (!$messages = $this->getStringArguments($node, 0 < $firstNamedArgumentIndex ? 0 : 'message')) {
            return null;
        }

        $domain = $this->getStringArguments($node, 2 < $firstNamedArgumentIndex ? 2 : 'domain')[0] ?? null;

        foreach ($messages as $message) {
            $this->addMessageToCatalogue($message, $domain, $node->getStartLine());
        }

        return null;
    }

    public function afterTraverse(array $nodes): ?Node
    {
        return null;
    }
}
