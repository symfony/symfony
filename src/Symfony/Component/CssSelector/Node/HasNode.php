<?php

namespace Symfony\Component\CssSelector\Node;

class HasNode extends AbstractNode
{
    private $selector;
    private $subSelector;

    public function __construct(NodeInterface $selector, NodeInterface $subSelector)
    {
        $this->selector = $selector;
        $this->subSelector = $subSelector;
    }

    public function getSelector(): NodeInterface
    {
        return $this->selector;
    }

    public function getSubSelector(): NodeInterface
    {
        return $this->subSelector;
    }

    /**
     * {@inheritdoc}
     */
    public function getSpecificity(): Specificity
    {
        return $this->selector->getSpecificity()->plus($this->subSelector->getSpecificity());
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return sprintf('%s[%s:has(%s)]', $this->getNodeName(), $this->selector, $this->subSelector);
    }
}
