<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\XPath;

use Symfony\Component\CssSelector\Exception\ExpressionErrorException;
use Symfony\Component\CssSelector\Node\FunctionNode;
use Symfony\Component\CssSelector\Node\NodeInterface;
use Symfony\Component\CssSelector\Node\SelectorNode;
use Symfony\Component\CssSelector\Parser\Parser;
use Symfony\Component\CssSelector\Parser\ParserInterface;
use Symfony\Component\CssSelector\XPath\Extension;

/**
 * XPath expression translator interface.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Translator implements TranslatorInterface
{
    /**
     * @var ParserInterface
     */
    private $parser;

    /**
     * @var array
     */
    private $nodeTranslators;

    /**
     * @var array
     */
    private $combinationTranslators;

    /**
     * @var array
     */
    private $functionTranslators;

    /**
     * @var array
     */
    private $pseudoClassTranslators;

    /**
     * @var array
     */
    private $attributeMatchingTranslators;

    /**
     * Constructor.
     *
     * @param ParserInterface|null $parser
     */
    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?: new Parser();
        $this->nodeTranslators = array();
        $this->combinationTranslators = array();
        $this->functionTranslators = array();
        $this->pseudoClassTranslators = array();
        $this->attributeMatchingTranslators = array();

        $this
            ->registerExtension(new Extension\NodeExtension($this))
            ->registerExtension(new Extension\CombinationExtension($this))
            ->registerExtension(new Extension\FunctionExtension($this))
            ->registerExtension(new Extension\PseudoClassExtension($this))
            ->registerExtension(new Extension\AttributeMatchingExtension($this))
        ;
    }

    /**
     * @param string $element
     *
     * @return string
     */
    public static function getXpathLiteral($element)
    {
        if (false === strpos($element, "'")) {
            return "'".$element."'";
        }

        if (false === strpos($element, '"')) {
            return '"'.$element.'"';
        }

        return sprintf('concat(%s)', implode(',', function ($part) {
            return Translator::getXpathLiteral($part);
        }, preg_split("('+)", $element)));
    }

    /**
     * {@inheritdoc}
     */
    public function cssToXPath($cssExpr, $prefix = 'descendant-or-self::')
    {
        $selectors = $this->parser->parse($cssExpr);

        /** @var SelectorNode $selector */
        foreach ($selectors as $selector) {
            if (null !== $selector->getPseudoElement()) {
                throw new ExpressionErrorException('Pseudo-elements are not supported.');
            }
        }

        $translator = $this;

        return implode(' | ', array_map(function (SelectorNode $selector) use ($translator, $prefix) {
            return $translator->selectorToXPath($selector, $prefix);
        }, $selectors));
    }

    /**
     * {@inheritdoc}
     */
    public function selectorToXPath(SelectorNode $selector, $prefix = 'descendant-or-self::')
    {
        return ($prefix ?: '').$this->nodeToXPath($selector);
    }

    /**
     * Registers an extension.
     *
     * @param Extension\ExtensionInterface $extension
     *
     * @return Translator
     */
    public function registerExtension(Extension\ExtensionInterface $extension)
    {
        $this->nodeTranslators = array_merge($this->nodeTranslators, $extension->getNodeTranslators());
        $this->combinationTranslators = array_merge($this->combinationTranslators, $extension->getCombinationTranslators());
        $this->functionTranslators = array_merge($this->functionTranslators, $extension->getFunctionTranslators());
        $this->pseudoClassTranslators = array_merge($this->pseudoClassTranslators, $extension->getPseudoClassTranslators());
        $this->attributeMatchingTranslators = array_merge($this->attributeMatchingTranslators, $extension->getAttributeMatchingTranslators());

        return $this;
    }

    /**
     * @param NodeInterface $node
     *
     * @return XPathExpr
     *
     * @throws \InvalidArgumentException
     */
    public function nodeToXPath(NodeInterface $node)
    {
        if (!isset($this->nodeTranslators[$node->getNodeName()])) {
            throw new \InvalidArgumentException();
        }

        return call_user_func($this->nodeTranslators[$node->getNodeName()], $node);
    }

    /**
     * @param string        $combiner
     * @param NodeInterface $xpath
     * @param NodeInterface $combinedXpath
     *
     * @return XPathExpr
     *
     * @throws \InvalidArgumentException
     */
    public function addCombination($combiner, NodeInterface $xpath, NodeInterface $combinedXpath)
    {
        if (!isset($this->combinationTranslators[$combiner])) {
            throw new \InvalidArgumentException();
        }

        return call_user_func($this->combinationTranslators[$combiner], $xpath, $combinedXpath);
    }

    /**
     * @param XPathExpr $xpath
     * @param FunctionNode $function
     *
     * @return XPathExpr
     *
     * @throws \InvalidArgumentException
     */
    public function addFunction(XPathExpr $xpath, FunctionNode $function)
    {
        if (!isset($this->functionTranslators[$function->getName()])) {
            throw new \InvalidArgumentException();
        }

        return call_user_func($this->functionTranslators[$function->getName()], $xpath, $function);
    }

    /**
     * @param XPathExpr $xpath
     * @param string    $pseudoClass
     *
     * @return XPathExpr
     *
     * @throws \InvalidArgumentException
     */
    public function addPseudoClass(XPathExpr $xpath, $pseudoClass)
    {
        if (!isset($this->pseudoClassTranslators[$pseudoClass])) {
            throw new \InvalidArgumentException();
        }

        return call_user_func($this->pseudoClassTranslators[$pseudoClass], $xpath);
    }

    /**
     * @param XPathExpr $xpath
     * @param string    $operator
     * @param string    $attribute
     * @param string    $value
     *
     * @throws \InvalidArgumentException
     *
     * @return XPathExpr
     */
    public function addAttributeMatching(XPathExpr $xpath, $operator, $attribute, $value)
    {
        if (!isset($this->attributeMatchingTranslators[$operator])) {
            throw new \InvalidArgumentException();
        }

        return call_user_func($this->attributeMatchingTranslators[$operator], $xpath, $attribute, $value);
    }
}
