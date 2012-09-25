<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\NodeVisitor;

use Symfony\Bridge\Twig\Node\TransNode;

/**
 * TranslationNodeVisitor extracts translation messages.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TranslationNodeVisitor implements \Twig_NodeVisitorInterface
{
    private $enabled = false;
    private $messages = array();

    public function enable()
    {
        $this->enabled = true;
        $this->messages = array();
    }

    public function disable()
    {
        $this->enabled = false;
        $this->messages = array();
    }

    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(\Twig_NodeInterface $node, \Twig_Environment $env)
    {
        if (!$this->enabled) {
            return $node;
        }

        if (
            $node instanceof \Twig_Node_Expression_Filter &&
            'trans' === $node->getNode('filter')->getAttribute('value') &&
            $node->getNode('node') instanceof \Twig_Node_Expression_Constant
        ) {
            // extract constant nodes with a trans filter
            $this->messages[] = array(
                $node->getNode('node')->getAttribute('value'),
                $node->getNode('arguments')->hasNode(1) ? $node->getNode('arguments')->getNode(1)->getAttribute('value') : null,
            );
        } elseif (
            $node instanceof \Twig_Node_Expression_Filter &&
            'transchoice' === $node->getNode('filter')->getAttribute('value') &&
            $node->getNode('node') instanceof \Twig_Node_Expression_Constant
        ) {
            // extract constant nodes with a trans filter
            $this->messages[] = array(
                $node->getNode('node')->getAttribute('value'),
                $node->getNode('arguments')->hasNode(2) ? $node->getNode('arguments')->getNode(2)->getAttribute('value') : null,
            );
        } elseif ($node instanceof TransNode) {
            // extract trans nodes
            $this->messages[] = array(
                $node->getNode('body')->getAttribute('data'),
                null === $node->getNode('domain') ? 'messages' : $node->getNode('domain')->getAttribute('value'),
            );
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    public function leaveNode(\Twig_NodeInterface $node, \Twig_Environment $env)
    {
        return $node;
    }

    /**
     * {@inheritdoc}
     */
    public function getPriority()
    {
        return -10;
    }
}
