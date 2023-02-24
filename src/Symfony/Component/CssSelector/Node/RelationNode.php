<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Node;

use Symfony\Component\CssSelector\Parser\Token;

/**
 * Represents a "<selector>:has(<subselector>)" node.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/scrapy/cssselect.
 *
 * @author Franck Ranaivo-Harisoa <franckranaivo@gmail.com>
 *
 * @internal
 */
class RelationNode extends AbstractNode
{
    private NodeInterface $selector;
    private NodeInterface $subSelector;
    private string $combinator;

    public function __construct(NodeInterface $selector, string $combinator, NodeInterface $subSelector)
    {
        $this->selector = $selector;
        $this->combinator = $combinator;
        $this->subSelector = $subSelector;
    }

    public function getSelector(): NodeInterface
    {
        return $this->selector;
    }

    public function getCombinator(): string
    {
        return $this->combinator;
    }

    public function getSubSelector(): NodeInterface
    {
        return $this->subSelector;
    }

    public function getSpecificity(): Specificity
    {
        return $this->selector->getSpecificity()->plus($this->subSelector->getSpecificity());
    }

    public function __toString(): string
    {
        return sprintf('%s[%s:has(%s)]', $this->getNodeName(), $this->selector, $this->subSelector);
    }
}
