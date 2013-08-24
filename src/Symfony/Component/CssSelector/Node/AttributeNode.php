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

/**
 * Represents a "<selector>[<namespace>|<attribute> <operator> <value>]" node.
 *
 * This component is a port of the Python cssselector library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @since v2.3.0
 */
class AttributeNode extends AbstractNode
{
    /**
     * @var NodeInterface
     */
    private $selector;

    /**
     * @var string
     */
    private $namespace;

    /**
     * @var string
     */
    private $attribute;

    /**
     * @var string
     */
    private $operator;

    /**
     * @var string
     */
    private $value;

    /**
     * @param NodeInterface $selector
     * @param string        $namespace
     * @param string        $attribute
     * @param string        $operator
     * @param string        $value
     *
     * @since v2.3.0
     */
    public function __construct(NodeInterface $selector, $namespace, $attribute, $operator, $value)
    {
        $this->selector = $selector;
        $this->namespace = $namespace;
        $this->attribute = $attribute;
        $this->operator = $operator;
        $this->value = $value;
    }

    /**
     * @return NodeInterface
     *
     * @since v2.3.0
     */
    public function getSelector()
    {
        return $this->selector;
    }

    /**
     * @return string
     *
     * @since v2.3.0
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @return string
     *
     * @since v2.3.0
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * @return string
     *
     * @since v2.3.0
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * @return string
     *
     * @since v2.3.0
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getSpecificity()
    {
        return $this->selector->getSpecificity()->plus(new Specificity(0, 1, 0));
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function __toString()
    {
        $attribute = $this->namespace ? $this->namespace.'|'.$this->attribute : $this->attribute;

        return 'exists' === $this->operator
            ? sprintf('%s[%s[%s]]', $this->getNodeName(), $this->selector, $attribute)
            : sprintf("%s[%s[%s %s '%s']]", $this->getNodeName(), $this->selector, $attribute, $this->operator, $this->value);
    }
}
