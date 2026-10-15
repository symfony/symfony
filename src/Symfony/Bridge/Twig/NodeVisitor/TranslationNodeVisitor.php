<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\NodeVisitor;

use Symfony\Bridge\Twig\Node\SearchAndRenderBlockNode;
use Symfony\Bridge\Twig\Node\TransNode;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\Binary\ConcatBinary;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * TranslationNodeVisitor extracts translation messages.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TranslationNodeVisitor implements NodeVisitorInterface
{
    public const UNDEFINED_DOMAIN = '_undefined';

    private bool $enabled = false;
    private array $messages = [];

    public function enable(): void
    {
        $this->enabled = true;
        $this->messages = [];
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->messages = [];
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if (!$this->enabled) {
            return $node;
        }

        if (
            $node instanceof FilterExpression
            && \in_array($filter = $node->hasAttribute('twig_callable') ? $node->getAttribute('twig_callable')->getName() : $node->getNode('filter')->getAttribute('value'), ['trans', 'trans_html'], true)
            && $node->getNode('node') instanceof ConstantExpression
        ) {
            // extract constant nodes with a trans filter
            $this->messages[] = [
                $node->getNode('node')->getAttribute('value'),
                $this->getReadDomainFromArguments($node->getNode('arguments'), 'trans_html' === $filter ? 2 : 1),
            ];
        } elseif (
            $node instanceof FunctionExpression
            && 't' === $node->getAttribute('name')
        ) {
            $nodeArguments = $node->getNode('arguments');

            if ($nodeArguments->getIterator()->current() instanceof ConstantExpression) {
                $this->messages[] = [
                    $this->getReadMessageFromArguments($nodeArguments, 0),
                    $this->getReadDomainFromArguments($nodeArguments, 2),
                ];
            }
        } elseif ($node instanceof TransNode) {
            // extract trans nodes
            $this->messages[] = [
                $node->getNode('body')->getAttribute('data'),
                $node->hasNode('domain') ? $this->getReadDomainFromNode($node->getNode('domain')) : null,
            ];
        } elseif (
            $node instanceof FilterExpression
            && \in_array($filter = $node->hasAttribute('twig_callable') ? $node->getAttribute('twig_callable')->getName() : $node->getNode('filter')->getAttribute('value'), ['trans', 'trans_html'], true)
            && $node->getNode('node') instanceof ConcatBinary
            && $message = $this->getConcatValueFromNode($node->getNode('node'), null)
        ) {
            $this->messages[] = [
                $message,
                $this->getReadDomainFromArguments($node->getNode('arguments'), 'trans_html' === $filter ? 2 : 1),
            ];
        } elseif ($node instanceof SearchAndRenderBlockNode) {
            array_push($this->messages, ...$this->getFormMessagesFromNode($node));
        }

        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }

    private function getReadMessageFromArguments(Node $arguments, int $index): ?string
    {
        if ($arguments->hasNode('message')) {
            $argument = $arguments->getNode('message');
        } elseif ($arguments->hasNode($index)) {
            $argument = $arguments->getNode($index);
        } else {
            return null;
        }

        return $this->getReadMessageFromNode($argument);
    }

    private function getReadMessageFromNode(Node $node): ?string
    {
        if ($node instanceof ConstantExpression) {
            return $node->getAttribute('value');
        }

        return null;
    }

    private function getReadDomainFromArguments(Node $arguments, int $index): ?string
    {
        if ($arguments->hasNode('domain')) {
            $argument = $arguments->getNode('domain');
        } elseif ($arguments->hasNode($index)) {
            $argument = $arguments->getNode($index);
        } else {
            return null;
        }

        return $this->getReadDomainFromNode($argument);
    }

    private function getReadDomainFromNode(Node $node): ?string
    {
        if ($node instanceof ConstantExpression) {
            return $node->getAttribute('value');
        }

        if (
            $node instanceof FunctionExpression
            && 'constant' === $node->getAttribute('name')
        ) {
            $nodeArguments = $node->getNode('arguments');
            if ($nodeArguments->getIterator()->current() instanceof ConstantExpression) {
                $constantName = $nodeArguments->getIterator()->current()->getAttribute('value');
                if (\defined($constantName)) {
                    $value = \constant($constantName);
                    if (\is_string($value)) {
                        return $value;
                    }
                }
            }
        }

        return self::UNDEFINED_DOMAIN;
    }

    private function getFormMessagesFromNode(SearchAndRenderBlockNode $node): array
    {
        $names = match ($node->getAttribute('name')) {
            'form_label', 'form_widget' => ['label'],
            'form_help' => ['help'],
            'form_row' => ['label', 'help'],
            default => [],
        };

        if (!$names) {
            return [];
        }

        $arguments = $node->getNode('arguments');
        $isFormLabel = 'form_label' === $node->getAttribute('name');
        $variablesIndex = $isFormLabel ? 2 : 1;
        $variables = [];

        if ($arguments->hasNode($variablesIndex) && ($variablesNode = $arguments->getNode($variablesIndex)) instanceof ArrayExpression) {
            foreach ($variablesNode->getKeyValuePairs() as $pair) {
                if ($pair['key'] instanceof ConstantExpression) {
                    $variables[$pair['key']->getAttribute('value')] = $pair['value'];
                }
            }
        }

        // SearchAndRenderBlockNode keeps the "label" variable unless the label argument is a non-empty constant
        if ($isFormLabel && $arguments->hasNode(1) && ($label = $arguments->getNode(1)) instanceof ConstantExpression && !\in_array($label->getAttribute('value'), [null, false, ''], true)) {
            $variables['label'] = $label;
        }

        // without a "translation_domain" variable, the theme uses the option of the form, which is not known here
        $domain = null;

        if (isset($variables['translation_domain'])) {
            if ($variables['translation_domain'] instanceof ConstantExpression && false === $variables['translation_domain']->getAttribute('value')) {
                return [];
            }

            $domain = $this->getReadDomainFromNode($variables['translation_domain']);
        }

        $messages = [];

        foreach ($names as $name) {
            if (($variables[$name] ?? null) instanceof ConstantExpression && \is_string($message = $variables[$name]->getAttribute('value')) && '' !== $message) {
                $messages[] = [$message, $domain];
            }
        }

        return $messages;
    }

    private function getConcatValueFromNode(Node $node, ?string $value): ?string
    {
        if ($node instanceof ConcatBinary) {
            foreach ($node as $nextNode) {
                if ($nextNode instanceof ConcatBinary) {
                    $nextValue = $this->getConcatValueFromNode($nextNode, $value);
                    if (null === $nextValue) {
                        return null;
                    }
                    $value .= $nextValue;
                } elseif ($nextNode instanceof ConstantExpression) {
                    $value .= $nextNode->getAttribute('value');
                } else {
                    // this is a node we cannot process (variable, or translation in translation)
                    return null;
                }
            }
        } elseif ($node instanceof ConstantExpression) {
            $value .= $node->getAttribute('value');
        }

        return $value;
    }
}
