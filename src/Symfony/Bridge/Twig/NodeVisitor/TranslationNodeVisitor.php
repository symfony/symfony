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
    const UNDEFINED_DOMAIN = '_undefined';

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
                $this->getReadDomainFromArguments($node->getNode('arguments'), 1),
            );
        } elseif (
            $node instanceof \Twig_Node_Expression_Filter &&
            'transchoice' === $node->getNode('filter')->getAttribute('value') &&
            $node->getNode('node') instanceof \Twig_Node_Expression_Constant
        ) {
            // extract constant nodes with a trans filter
            $this->messages[] = array(
                $node->getNode('node')->getAttribute('value'),
                $this->getReadDomainFromArguments($node->getNode('arguments'), 2),
            );
        } elseif ($node instanceof TransNode) {
            // extract trans nodes
            $this->messages[] = array(
                $node->getNode('body')->getAttribute('data'),
                $this->getReadDomainFromNode($node->getNode('domain')),
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
        return 0;
    }

    /**
     * @param \Twig_Node $arguments
     * @param int        $index
     *
     * @return string|null
     */
    private function getReadDomainFromArguments(\Twig_Node $arguments, $index)
    {
        if ($arguments->hasNode('domain')) {
            $argument = $arguments->getNode('domain');
        } elseif ($arguments->hasNode($index)) {
            $argument = $arguments->getNode($index);
        } else {
            return;
        }

        return $this->getReadDomainFromNode($argument);
    }

    /**
     * @param \Twig_Node $node
     *
     * @return string|null
     */
    private function getReadDomainFromNode(\Twig_Node $node = null)
    {
        if (null === $node) {
            return;
        }

        if ($node instanceof \Twig_Node_Expression_Constant) {
            return $node->getAttribute('value');
        }

        return self::UNDEFINED_DOMAIN;
    }
}
