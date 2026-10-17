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
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;
use Symfony\Component\CssSelector\Node\AttributeNode;
use Symfony\Component\CssSelector\Node\ClassNode;
use Symfony\Component\CssSelector\Node\CombinedSelectorNode;
use Symfony\Component\CssSelector\Node\ElementNode;
use Symfony\Component\CssSelector\Node\FunctionNode;
use Symfony\Component\CssSelector\Node\HashNode;
use Symfony\Component\CssSelector\Node\MatchingNode;
use Symfony\Component\CssSelector\Node\NegationNode;
use Symfony\Component\CssSelector\Node\NodeInterface;
use Symfony\Component\CssSelector\Node\PseudoNode;
use Symfony\Component\CssSelector\Node\RelationNode;
use Symfony\Component\CssSelector\Node\SelectorNode;
use Symfony\Component\CssSelector\Node\SpecificityAdjustmentNode;
use Symfony\Component\CssSelector\Parser\Parser;
use Symfony\Component\CssSelector\XPath\Translator;
use Symfony\Component\CssSelector\XPath\XPathExpr;

/**
 * XPath expression translator function extension.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class FunctionExtension extends AbstractExtension
{
    /**
     * @var \WeakReference<Translator>|null
     */
    private ?\WeakReference $translator;

    public function __construct(?Translator $translator = null)
    {
        $this->translator = $translator ? \WeakReference::create($translator) : null;
    }

    public function getFunctionTranslators(): array
    {
        return [
            'nth-child' => $this->translateNthChild(...),
            'nth-last-child' => $this->translateNthLastChild(...),
            'nth-of-type' => $this->translateNthOfType(...),
            'nth-last-of-type' => $this->translateNthLastOfType(...),
            'contains' => $this->translateContains(...),
            'lang' => $this->translateLang(...),
        ];
    }

    /**
     * @throws ExpressionErrorException
     */
    public function translateNthChild(XPathExpr $xpath, FunctionNode $function, bool $last = false, bool $addNameTest = true): XPathExpr
    {
        try {
            [$a, $b] = Parser::parseSeries($function->getArguments());
        } catch (SyntaxErrorException $e) {
            throw new ExpressionErrorException(\sprintf('Invalid series: "%s".', implode('", "', $function->getArguments())), 0, $e);
        }

        $xpath->addStarPrefix();
        if ($addNameTest) {
            $xpath->addNameTest();
        }

        if ($function->getSelectorList()) {
            $translator = $this->translator?->get() ?? throw new ExpressionErrorException('The translator is not set.');
            $selectorCondition = $this->selectorListCondition($function->getSelectorList(), $translator);
            $siblings = $last ? 'following-sibling' : 'preceding-sibling';
            $index = \sprintf('count(%s::*[%s]) + 1', $siblings, $selectorCondition);

            return $xpath
                ->addCondition($selectorCondition)
                ->addCondition($this->nthCondition($index, $a, $b));
        }

        if (0 === $a) {
            return $xpath->addCondition('position() = '.($last ? 'last() - '.($b - 1) : $b));
        }

        if ($a < 0) {
            if ($b < 1) {
                return $xpath->addCondition('false()');
            }

            $sign = '<=';
        } else {
            $sign = '>=';
        }

        $expr = 'position()';

        if ($last) {
            $expr = 'last() - '.$expr;
            --$b;
        }

        if (0 !== $b) {
            $expr .= ' - '.$b;
        }

        $conditions = [\sprintf('%s %s 0', $expr, $sign)];

        if (1 !== $a && -1 !== $a) {
            $conditions[] = \sprintf('(%s) mod %d = 0', $expr, $a);
        }

        return $xpath->addCondition(implode(' and ', $conditions));

        // todo: handle an+b, odd, even
        // an+b means every-a, plus b, e.g., 2n+1 means odd
        // 0n+b means b
        // n+0 means a=1, i.e., all elements
        // an means every a elements, i.e., 2n means even
        // -n means -1n
        // -1n+6 means elements 6 and previous
    }

    public function translateNthLastChild(XPathExpr $xpath, FunctionNode $function): XPathExpr
    {
        return $this->translateNthChild($xpath, $function, true);
    }

    public function translateNthOfType(XPathExpr $xpath, FunctionNode $function): XPathExpr
    {
        return $this->translateNthChild($xpath, $function, false, false);
    }

    /**
     * @throws ExpressionErrorException
     */
    public function translateNthLastOfType(XPathExpr $xpath, FunctionNode $function): XPathExpr
    {
        if ('*' === $xpath->getElement()) {
            throw new ExpressionErrorException('"*:nth-of-type()" is not implemented.');
        }

        return $this->translateNthChild($xpath, $function, true, false);
    }

    /**
     * @throws ExpressionErrorException
     */
    public function translateContains(XPathExpr $xpath, FunctionNode $function): XPathExpr
    {
        $arguments = $function->getArguments();
        foreach ($arguments as $token) {
            if (!($token->isString() || $token->isIdentifier())) {
                throw new ExpressionErrorException('Expected a single string or identifier for :contains(), got '.implode(', ', $arguments));
            }
        }

        return $xpath->addCondition(\sprintf(
            'contains(string(.), %s)',
            Translator::getXpathLiteral($arguments[0]->getValue())
        ));
    }

    /**
     * @throws ExpressionErrorException
     */
    public function translateLang(XPathExpr $xpath, FunctionNode $function): XPathExpr
    {
        $arguments = $function->getArguments();
        foreach ($arguments as $token) {
            if (!($token->isString() || $token->isIdentifier())) {
                throw new ExpressionErrorException('Expected a single string or identifier for :lang(), got '.implode(', ', $arguments));
            }
        }

        return $xpath->addCondition(\sprintf(
            'lang(%s)',
            Translator::getXpathLiteral($arguments[0]->getValue())
        ));
    }

    public function getName(): string
    {
        return 'function';
    }

    /**
     * @param SelectorNode[] $selectors
     */
    private function selectorListCondition(array $selectors, Translator $translator): string
    {
        return '('.implode(') or (', array_map(fn (SelectorNode $selector) => $this->nodeCondition($selector, $translator), $selectors)).')';
    }

    private function nodeCondition(NodeInterface $node, Translator $translator): string
    {
        if ($node instanceof SelectorNode) {
            if (null !== $node->getPseudoElement()) {
                return 'false()';
            }

            return $this->nodeCondition($node->getTree(), $translator);
        }

        if ($node instanceof CombinedSelectorNode) {
            $left = $this->nodeCondition($node->getSelector(), $translator);
            $right = $this->nodeCondition($node->getSubSelector(), $translator);
            $axis = match ($node->getCombinator()) {
                '>' => 'parent::*',
                ' ' => 'ancestor::*',
                '+' => 'preceding-sibling::*[1]',
                '~' => 'preceding-sibling::*',
                default => throw new ExpressionErrorException(\sprintf('Combiner "%s" not supported.', $node->getCombinator())),
            };

            return \sprintf('(%s) and boolean(%s[%s])', $right, $axis, $left);
        }

        if ($node instanceof MatchingNode || $node instanceof SpecificityAdjustmentNode) {
            $conditions = array_map(fn (NodeInterface $argument) => $this->nodeCondition($argument, $translator), $node->arguments);

            return $conditions
                ? \sprintf('(%s) and (%s)', $this->nodeCondition($node->selector, $translator), implode(' or ', $conditions))
                : 'false()';
        }

        if ($node instanceof NegationNode) {
            return \sprintf('(%s) and not(%s)', $this->nodeCondition($node->getSelector(), $translator), $this->nodeCondition($node->getSubSelector(), $translator));
        }

        if ($node instanceof FunctionNode && \in_array($node->getName(), ['nth-child', 'nth-last-child'], true)) {
            $selectorCondition = $this->nodeCondition($node->getSelector(), $translator);

            try {
                [$a, $b] = Parser::parseSeries($node->getArguments());
            } catch (SyntaxErrorException $e) {
                throw new ExpressionErrorException(\sprintf('Invalid series: "%s".', implode('", "', $node->getArguments())), 0, $e);
            }

            $filterCondition = $node->getSelectorList()
                ? $this->selectorListCondition($node->getSelectorList(), $translator)
                : 'true()';
            $siblings = 'nth-last-child' === $node->getName() ? 'following-sibling' : 'preceding-sibling';
            $index = \sprintf('count(%s::*[%s]) + 1', $siblings, $filterCondition);

            return \sprintf('(%s) and (%s) and (%s)', $selectorCondition, $filterCondition, $this->nthCondition($index, $a, $b));
        }

        if ($node instanceof PseudoNode && \in_array($node->getIdentifier(), ['first-child', 'last-child', 'only-child', 'first-of-type', 'last-of-type', 'only-of-type'], true)) {
            $xpath = $translator->nodeToXPath($node->getSelector());
            $sameElement = \in_array($node->getIdentifier(), ['first-of-type', 'last-of-type', 'only-of-type'], true) ? $this->sameElementCondition($xpath, $node->getIdentifier()) : '';
            $selectorCondition = $this->nodeCondition($node->getSelector(), $translator);
            $condition = match ($node->getIdentifier()) {
                'first-child' => 'count(preceding-sibling::*) = 0',
                'last-child' => 'count(following-sibling::*) = 0',
                'only-child' => 'count(preceding-sibling::*) = 0 and count(following-sibling::*) = 0',
                'first-of-type' => 'count(preceding-sibling::*['.$sameElement.']) = 0',
                'last-of-type' => 'count(following-sibling::*['.$sameElement.']) = 0',
                'only-of-type' => 'count(preceding-sibling::*['.$sameElement.']) = 0 and count(following-sibling::*['.$sameElement.']) = 0',
            };

            return \sprintf('(%s) and (%s)', $selectorCondition, $condition);
        }

        if ($node instanceof FunctionNode && \in_array($node->getName(), ['nth-of-type', 'nth-last-of-type'], true)) {
            $xpath = $translator->nodeToXPath($node->getSelector());
            $sameElement = $this->sameElementCondition($xpath, $node->getName().'()');
            $selectorCondition = $this->nodeCondition($node->getSelector(), $translator);
            $siblings = 'nth-last-of-type' === $node->getName() ? 'following-sibling' : 'preceding-sibling';

            try {
                [$a, $b] = Parser::parseSeries($node->getArguments());
            } catch (SyntaxErrorException $e) {
                throw new ExpressionErrorException(\sprintf('Invalid series: "%s".', implode('", "', $node->getArguments())), 0, $e);
            }

            $index = \sprintf('count(%s::*[%s]) + 1', $siblings, $sameElement);

            return \sprintf('(%s) and (%s)', $selectorCondition, $this->nthCondition($index, $a, $b));
        }

        if ($node instanceof ClassNode) {
            $attribute = $translator->addAttributeMatching(new XPathExpr(), '~=', '@class', $node->getName())->getCondition();

            return \sprintf('(%s) and (%s)', $this->nodeCondition($node->getSelector(), $translator), $attribute);
        }

        if ($node instanceof HashNode) {
            $attribute = $translator->addAttributeMatching(new XPathExpr(), '=', '@id', $node->getId())->getCondition();

            return \sprintf('(%s) and (%s)', $this->nodeCondition($node->getSelector(), $translator), $attribute);
        }

        if ($node instanceof AttributeNode) {
            $attributeNode = new AttributeNode(new ElementNode(), $node->getNamespace(), $node->getAttribute(), $node->getOperator(), $node->getValue());
            $condition = $translator->nodeToXPath($attributeNode)->getCondition();

            return \sprintf('(%s) and (%s)', $this->nodeCondition($node->getSelector(), $translator), $condition);
        }

        if ($node instanceof RelationNode) {
            $conditions = [];
            foreach ($node->getArguments() as [$combinator, $subSelector]) {
                $relativeXpath = (string) $translator->addRelativeCombination($combinator, new ElementNode(), $subSelector);
                $conditions[] = substr($relativeXpath, 2, -1);
            }

            return \sprintf('(%s) and (%s)', $this->nodeCondition($node->getSelector(), $translator), implode(' or ', $conditions));
        }

        if ($node instanceof FunctionNode) {
            $function = $translator->addFunction(new XPathExpr(), $node)->getCondition();

            return \sprintf('(%s) and (%s)', $this->nodeCondition($node->getSelector(), $translator), $function);
        }

        if ($node instanceof PseudoNode) {
            $pseudo = $translator->addPseudoClass(new XPathExpr(), $node->getIdentifier())->getCondition();

            return \sprintf('(%s) and (%s)', $this->nodeCondition($node->getSelector(), $translator), $pseudo);
        }

        $xpath = $translator->nodeToXPath($node);
        $xpath->addNameTest();

        return $this->selfCondition($xpath);
    }

    private function selfCondition(XPathExpr $xpath): string
    {
        return 'self::'.$xpath->getElement().($xpath->getCondition() ? '['.$xpath->getCondition().']' : '');
    }

    private function sameElementCondition(XPathExpr $xpath, string $pseudoClass): string
    {
        if ('*' === $xpath->getElement()) {
            throw new ExpressionErrorException(\sprintf('"*:%s" is not implemented.', $pseudoClass));
        }

        return 'name() = '.Translator::getXpathLiteral($xpath->getElement());
    }

    private function nthCondition(string $index, int $a, int $b): string
    {
        if (0 === $a) {
            return $index.' = '.$b;
        }

        if ($a < 0 && $b < 1) {
            return 'false()';
        }

        $expression = '('.$index.') - '.$b;
        $conditions = [\sprintf('%s %s 0', $expression, $a < 0 ? '<=' : '>=')];
        if (1 !== $a && -1 !== $a) {
            $conditions[] = \sprintf('(%s) mod %d = 0', $expression, $a);
        }

        return implode(' and ', $conditions);
    }
}
