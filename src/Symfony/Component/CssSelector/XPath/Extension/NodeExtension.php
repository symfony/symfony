<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\XPath\Extension;

use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\Node;
use Symfony\Component\CssSelector\XPath\Translator;
use Symfony\Component\CssSelector\XPath\XPathExpr;

/**
 * XPath expression translator node extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class NodeExtension extends AbstractExtension
{
    public const ELEMENT_NAME_IN_LOWER_CASE = 1;
    public const ATTRIBUTE_NAME_IN_LOWER_CASE = 2;
    public const ATTRIBUTE_VALUE_IN_LOWER_CASE = 4;

    public function __construct(
        private int $flags = 0,
    ) {
    }

    /**
     * @return $this
     */
    public function setFlag(int $flag, bool $on): static
    {
        if ($on && !$this->hasFlag($flag)) {
            $this->flags += $flag;
        }

        if (!$on && $this->hasFlag($flag)) {
            $this->flags -= $flag;
        }

        return $this;
    }

    public function hasFlag(int $flag): bool
    {
        return (bool) ($this->flags & $flag);
    }

    public function getNodeTranslators(): array
    {
        return [
            'Selector' => $this->translateSelector(...),
            'CombinedSelector' => $this->translateCombinedSelector(...),
            'Negation' => $this->translateNegation(...),
            'Matching' => $this->translateMatching(...),
            'SpecificityAdjustment' => $this->translateSpecificityAdjustment(...),
            'Function' => $this->translateFunction(...),
            'Pseudo' => $this->translatePseudo(...),
            'Attribute' => $this->translateAttribute(...),
            'Class' => $this->translateClass(...),
            'Hash' => $this->translateHash(...),
            'Element' => $this->translateElement(...),
        ];
    }

    public function translateSelector(Node\SelectorNode $node, Translator $translator): XPathExpr
    {
        return $translator->nodeToXPath($node->getTree());
    }

    public function translateCombinedSelector(Node\CombinedSelectorNode $node, Translator $translator): XPathExpr
    {
        return $translator->addCombination($node->getCombinator(), $node->getSelector(), $node->getSubSelector());
    }

    public function translateNegation(Node\NegationNode $node, Translator $translator): XPathExpr
    {
        $xpath = $translator->nodeToXPath($node->getSelector());
        $subXpath = $translator->nodeToXPath($node->getSubSelector());
        $subXpath->addNameTest();

        if ($subXpath->getCondition()) {
            return $xpath->addCondition(\sprintf('not(%s)', $subXpath->getCondition()));
        }

        return $xpath->addCondition('0');
    }

    public function translateMatching(Node\MatchingNode $node, Translator $translator): XPathExpr
    {
        return $this->translateMatchingOrSpecificityAdjustment($node->selector, $node->arguments, $translator);
    }

    public function translateSpecificityAdjustment(Node\SpecificityAdjustmentNode $node, Translator $translator): XPathExpr
    {
        return $this->translateMatchingOrSpecificityAdjustment($node->selector, $node->arguments, $translator);
    }

    /**
     * @param array<Node\NodeInterface> $arguments
     */
    private function translateMatchingOrSpecificityAdjustment(Node\NodeInterface $selector, array $arguments, Translator $translator): XPathExpr
    {
        $xpath = $translator->nodeToXPath($selector);
        $type = $this->getElementType($selector);

        $conditions = [];
        foreach ($arguments as $argument) {
            if ('' === $condition = $this->selectorCondition($argument, $translator, $type)) {
                return $xpath;
            }

            $conditions[] = $condition;
        }

        if ($conditions) {
            $xpath->addCondition(1 === \count($conditions) ? $conditions[0] : '('.implode(') or (', $conditions).')');
        }

        return $xpath;
    }

    public function translateFunction(Node\FunctionNode $node, Translator $translator): XPathExpr
    {
        $xpath = $translator->nodeToXPath($node->getSelector());

        return $translator->addFunction($xpath, $node);
    }

    public function translatePseudo(Node\PseudoNode $node, Translator $translator): XPathExpr
    {
        $xpath = $translator->nodeToXPath($node->getSelector());

        return $translator->addPseudoClass($xpath, $node->getIdentifier());
    }

    public function translateAttribute(Node\AttributeNode $node, Translator $translator): XPathExpr
    {
        $name = $node->getAttribute();
        $safe = $this->isSafeName($name);

        if ($this->hasFlag(self::ATTRIBUTE_NAME_IN_LOWER_CASE)) {
            $name = strtolower($name);
        }

        if ($node->getNamespace()) {
            $name = \sprintf('%s:%s', $node->getNamespace(), $name);
            $safe = $safe && $this->isSafeName($node->getNamespace());
        }

        $attribute = $safe ? '@'.$name : \sprintf('attribute::*[name() = %s]', Translator::getXpathLiteral($name));
        $value = $node->getValue();
        $xpath = $translator->nodeToXPath($node->getSelector());

        if ($this->hasFlag(self::ATTRIBUTE_VALUE_IN_LOWER_CASE)) {
            $value = strtolower($value);
        }

        return $translator->addAttributeMatching($xpath, $node->getOperator(), $attribute, $value);
    }

    public function translateClass(Node\ClassNode $node, Translator $translator): XPathExpr
    {
        $xpath = $translator->nodeToXPath($node->getSelector());

        return $translator->addAttributeMatching($xpath, '~=', '@class', $node->getName());
    }

    public function translateHash(Node\HashNode $node, Translator $translator): XPathExpr
    {
        $xpath = $translator->nodeToXPath($node->getSelector());

        return $translator->addAttributeMatching($xpath, '=', '@id', $node->getId());
    }

    public function translateElement(Node\ElementNode $node): XPathExpr
    {
        $element = $node->getElement();

        if ($element && $this->hasFlag(self::ELEMENT_NAME_IN_LOWER_CASE)) {
            $element = strtolower($element);
        }

        if ($element) {
            $safe = $this->isSafeName($element);
        } else {
            $element = '*';
            $safe = true;
        }

        if ($node->getNamespace()) {
            $element = \sprintf('%s:%s', $node->getNamespace(), $element);
            $safe = $safe && $this->isSafeName($node->getNamespace());
        }

        $xpath = new XPathExpr('', $element);

        if (!$safe) {
            $xpath->addNameTest();
        }

        return $xpath;
    }

    public function getName(): string
    {
        return 'node';
    }

    private function hasNthOfType(Node\NodeInterface $node): bool
    {
        while ($node instanceof Node\FunctionNode || $node instanceof Node\PseudoNode || $node instanceof Node\ClassNode || $node instanceof Node\HashNode || $node instanceof Node\AttributeNode || $node instanceof Node\NegationNode || $node instanceof Node\MatchingNode || $node instanceof Node\SpecificityAdjustmentNode) {
            if ($node instanceof Node\FunctionNode && 'nth-of-type' === $node->getName()) {
                return true;
            }

            $node = $node instanceof Node\MatchingNode || $node instanceof Node\SpecificityAdjustmentNode ? $node->selector : $node->getSelector();
        }

        return false;
    }

    private function getElementType(Node\NodeInterface $node): string
    {
        while ($node instanceof Node\NegationNode || $node instanceof Node\FunctionNode || $node instanceof Node\PseudoNode || $node instanceof Node\ClassNode || $node instanceof Node\HashNode || $node instanceof Node\AttributeNode || $node instanceof Node\MatchingNode || $node instanceof Node\SpecificityAdjustmentNode) {
            $node = $node instanceof Node\MatchingNode || $node instanceof Node\SpecificityAdjustmentNode ? $node->selector : $node->getSelector();
        }

        return $node instanceof Node\ElementNode ? $this->translateElement($node)->getElement() : '*';
    }

    /**
     * Replaces position() and last() by tests on the siblings of the context node.
     */
    private function positionsToSiblings(string $condition, string $element): string
    {
        // [1] stops at the first sibling found: count() is used only when An+B needs it
        $first = \sprintf('not(preceding-sibling::%s[1])', $element);
        $last = \sprintf('not(following-sibling::%s[1])', $element);
        $depth = 0;
        $result = '';

        foreach (preg_split('/(\'[^\']*+\'|"[^"]*+"|[\[\]]|position\(\) = (?:1|last\(\)(?: - 0)?)(?![\d-]| -)|last\(\) = 1(?!\d)|last\(\) - position\(\)|position\(\)|last\(\))/', $condition, -1, \PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if ('[' === $part) {
                ++$depth;
            } elseif (']' === $part) {
                --$depth;
            } elseif (!$depth) {
                $part = match ($part) {
                    'position() = 1' => $first,
                    'position() = last()', 'position() = last() - 0' => $last,
                    'last() = 1' => $first.' and '.$last,
                    'last() - position()' => \sprintf('count(following-sibling::%s)', $element),
                    'position()' => \sprintf('(count(preceding-sibling::%s) + 1)', $element),
                    'last()' => \sprintf('(count(preceding-sibling::%1$s) + count(following-sibling::%1$s) + 1)', $element),
                    default => $part,
                };
            }

            $result .= $part;
        }

        return $result;
    }

    private function isSafeName(string $name): bool
    {
        return 0 < preg_match('~^[a-zA-Z_][a-zA-Z0-9_.-]*$~', $name);
    }

    /**
     * Returns the condition for the context node to match the selector, using reverse axes for its combinators.
     */
    private function selectorCondition(Node\NodeInterface $node, Translator $translator, string $type = '*'): string
    {
        if ($node instanceof Node\SelectorNode) {
            $node = $node->getTree();
        }

        if ($node instanceof Node\CombinedSelectorNode) {
            if ('' !== $condition = $this->selectorCondition($node->getSelector(), $translator)) {
                $condition = '['.$condition.']';
            }

            // a predicate on a sibling axis is evaluated on every sibling unless [1] is the last one
            $axis = match ($node->getCombinator()) {
                '>' => 'parent::*'.$condition,
                ' ' => 'ancestor::*'.$condition,
                '+' => 'preceding-sibling::*[1]'.('' !== $condition ? '/self::*'.$condition : ''),
                '~' => 'preceding-sibling::*'.$condition.'[1]',
                default => throw new ExpressionErrorException(\sprintf('Combiner "%s" not supported.', $node->getCombinator())),
            };

            if ('' === $condition = $this->selectorCondition($node->getSubSelector(), $translator, $type)) {
                return $axis;
            }

            return \sprintf('(%s) and (%s)', $condition, $axis);
        }

        $xpath = $translator->nodeToXPath($node);

        if (str_starts_with($xpath, '*/')) {
            $siblings = $xpath->getElement();

            if ('*' === $siblings && $this->hasNthOfType($node)) {
                $siblings = $type;
            }

            $xpath = new XPathExpr('', $xpath->getElement(), $this->positionsToSiblings($xpath->getCondition(), $siblings));
        }

        return $xpath->addNameTest()->getCondition();
    }
}
