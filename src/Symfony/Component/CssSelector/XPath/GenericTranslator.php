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

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Symfony\Component\CssSelector\Node\AttributeNode;
use Symfony\Component\CssSelector\Node\NodeInterface;
use Symfony\Component\CssSelector\Node\PseudoNode;
use Symfony\Component\CssSelector\Node\SelectorNode;
use Symfony\Component\CssSelector\Parser\Parser;
use Symfony\Component\CssSelector\Parser\ParserInterface;

/**
 * XPath expression translator interface.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class GenericTranslator implements TranslatorInterface
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
    private $combinatorTranslators;

    /**
     * @var array
     */
    private $functionTranslators;

    /**
     * @var array
     */
    private $attributeTranslators;

    /**
     * Constructor.
     *
     * @param ParserInterface|null $parser
     */
    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?: new Parser();
        $this->nodeTranslators = array();
        $this->combinatorTranslators = array();
        $this->functionTranslators = array();
        $this->attributeTranslators = array();
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
            return GenericTranslator::getXpathLiteral($part);
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
        return ($prefix ?: '').$this->components->getNodeTranslator($selector->getNodeName())->toXPath($selector);
    }

    public function nodeToXPath(NodeInterface $node)
    {
        if (!isset($this->nodeTranslators[$node->getNodeName()])) {
            throw new \InvalidArgumentException();
        }


    }

    public function addFunctionCondition(XPathExpr $xpath, FunctionNode $function, $last = null, $addNameTest = null)
    {

    }

    public function addPseudoCondition(XPathExpr $xpath, PseudoNode $seudo)
    {

    }

    public function addAttributeCondition(XPathExpr $xpath, AttributeNode $attribute)
    {

    }
}
