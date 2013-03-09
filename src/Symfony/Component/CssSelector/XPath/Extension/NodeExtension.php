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

use Symfony\Component\CssSelector\Node;
use Symfony\Component\CssSelector\XPath\Translator;
use Symfony\Component\CssSelector\XPath\XPathExpr;

/**
 * XPath expression translator node extension.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class NodeExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getNodeTranslators()
    {
        return array(
            'CombinedSelector' => array($this, 'translateCombinedSelector'),
            'Negation'         => array($this, 'translateNegation'),
            'Function'         => array($this, 'translateFunction'),
            'Pseudo'           => array($this, 'translatePseudo'),
            'Attribute'        => array($this, 'translateAttribute'),
            'Class'            => array($this, 'translateClass'),
            'Hash'             => array($this, 'translateHash'),
            'Element'          => array($this, 'translateElement'),
        );
    }

    /**
     * @param Node\CombinedSelectorNode $node
     *
     * @return XPathExpr
     */
    public function translateCombinedSelector(Node\CombinedSelectorNode $node)
    {
        return $this->translator->addCombination($node->getCombinator(), $node->getSelector(), $node->getSubSelector());
    }

    /**
     * @param Node\NegationNode $node
     *
     * @return XPathExpr
     */
    public function translateNegation(Node\NegationNode $node)
    {
        $xpath = $this->translator->nodeToXPath($node->getSelector());
        $subXpath = $this->translator->nodeToXPath($node->getSubSelector());
        $subXpath->addNameTest();

        if ($subXpath->getCondition()) {
            return $xpath->addCondition(sprintf('not(%s)', $subXpath->getCondition()));
        }

        return $xpath->addCondition('0');
    }

    /**
     * @param Node\FunctionNode $node
     *
     * @return XPathExpr
     */
    public function translateFunction(Node\FunctionNode $node)
    {
        $xpath = $this->translator->nodeToXPath($node->getSelector());

        return $this->translator->addFunction($xpath, $node);
    }

    /**
     * @param Node\PseudoNode $node
     *
     * @return XPathExpr
     */
    public function translatePseudo(Node\PseudoNode $node)
    {
        $xpath = $this->translator->nodeToXPath($node->getSelector());

        return $this->translator->addPseudoClass($xpath, $node->getIdentifier());
    }

    /**
     * @param Node\AttributeNode $node
     *
     * @return XPathExpr
     */
    public function translateAttribute(Node\AttributeNode $node)
    {
        // todo: lowercase name in html
        $name = $node->getAttribute();
        $safe = $this->isSafeName($name);

        if ($node->getNamespace()) {
            $name = sprintf('%s:%s', $node->getNamespace(), $name);
            $safe = $safe && $this->isSafeName($node->getNamespace());
        }

        $attribute = $safe ? '@'.$name : sprintf('attribute::*[name() = %s]', Translator::getXpathLiteral($name));
        // todo: lowercase value in html
        $value = $node->getValue();
        $xpath = $this->translator->nodeToXPath($node->getSelector());

        return $this->translator->addAttributeMatching($xpath, $node->getOperator(), $attribute, $value);
    }

    /**
     * @param Node\ClassNode $node
     *
     * @return XPathExpr
     */
    public function translateClass(Node\ClassNode $node)
    {
        $xpath = $this->translator->nodeToXPath($node->getSelector());

        return $this->translator->addAttributeMatching($xpath, '~=', '@class', $node->getName());
    }

    /**
     * @param Node\HashNode $node
     *
     * @return XPathExpr
     */
    public function translateHash(Node\HashNode $node)
    {
        $xpath = $this->translator->nodeToXPath($node->getSelector());

        return $this->translator->addAttributeMatching($xpath, '=', '@id', $node->getId());
    }

    /**
     * @param Node\ElementNode $node
     *
     * @return XPathExpr
     */
    public function translateElement(Node\ElementNode $node)
    {
        $element = $node->getElement();

        if ($element) {
            // todo: lowercase in html
            $safe = $this->isSafeName($element);
        } else {
            $element = '*';
            $safe = true;
        }

        if ($node->getNamespace()) {
            $element = sprintf('%s:%s', $node->getNamespace(), $element);
            $safe = $safe && $this->isSafeName($node->getNamespace());
        }

        $xpath = new XPathExpr('', $element);

        if (!$safe) {
            $xpath->addNameTest();
        }

        return $xpath;
    }
}
