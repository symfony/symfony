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
     * @var TranslatorComponents
     */
    private $components;

    /**
     * Constructor.
     *
     * @param ParserInterface|null $parser
     */
    public function __construct(ParserInterface $parser = null)
    {
        $this->parser = $parser ?: new Parser();
        $this->components = new TranslatorComponents();

        $this->components
            ->registerNodeTranslator('Attribute', new Node\ClassTranslator($this->components))
            ->registerNodeTranslator('Class', new Node\ClassTranslator($this->components))
            ->registerNodeTranslator('CombinedSelector', new Node\ClassTranslator($this->components))
            ->registerNodeTranslator('Element', new Node\ClassTranslator($this->components))
            ->registerNodeTranslator('Function', new Node\ClassTranslator($this->components))
            ->registerNodeTranslator('Hash', new Node\HashTranslator($this->components))
            ->registerNodeTranslator('Negation', new Node\ElementTranslator($this->components))
            ->registerNodeTranslator('Pseudo', new Node\ElementTranslator($this->components))
            ->registerNodeTranslator('Selector', new Node\ElementTranslator($this->components))
        ;
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
                throw new ExpressionError('Pseudo-elements are not supported.');
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
}
