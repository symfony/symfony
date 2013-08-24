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
 * Represents a "<selector>#<id>" node.
 *
 * This component is a port of the Python cssselector library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @since v2.3.0
 */
class HashNode extends AbstractNode
{
    /**
     * @var NodeInterface
     */
    private $selector;

    /**
     * @var string
     */
    private $id;

    /**
     * @param NodeInterface $selector
     * @param string        $id
     *
     * @since v2.3.0
     */
    public function __construct(NodeInterface $selector, $id)
    {
        $this->selector = $selector;
        $this->id = $id;
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
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function getSpecificity()
    {
        return $this->selector->getSpecificity()->plus(new Specificity(1, 0, 0));
    }

    /**
     * {@inheritdoc}
     *
     * @since v2.3.0
     */
    public function __toString()
    {
        return sprintf('%s[%s#%s]', $this->getNodeName(), $this->selector, $this->id);
    }
}
