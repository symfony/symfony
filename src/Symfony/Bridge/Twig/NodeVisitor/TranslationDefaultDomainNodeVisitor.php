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
use Symfony\Bridge\Twig\Node\TransDefaultDomainNode;

/**
 * TranslationDefaultDomainNodeVisitor.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TranslationDefaultDomainNodeVisitor implements \Twig_NodeVisitorInterface
{
    private $domain;

    /**
     * {@inheritdoc}
     */
    public function enterNode(\Twig_NodeInterface $node, \Twig_Environment $env)
    {
        if ($node instanceof \Twig_Node_Module) {
            $this->domain = null;
        }

        if ($node instanceof TransDefaultDomainNode) {
            if ($node->getNode('expr') instanceof \Twig_Node_Expression_Constant) {
                $this->domain = $node->getNode('expr');

                return $node;
            } else {
                $var = $env->getParser()->getVarName();
                $name = new \Twig_Node_Expression_AssignName($var, $node->getLine());
                $this->domain = new \Twig_Node_Expression_Name($var, $node->getLine());

                return new \Twig_Node_Set(false, new \Twig_Node(array($name)), new \Twig_Node(array($node->getNode('expr'))), $node->getLine());
            }
        }

        if (null === $this->domain) {
            return $node;
        }

        if ($node instanceof \Twig_Node_Expression_Filter && in_array($node->getNode('filter')->getAttribute('value'), array('trans', 'transchoice'))) {
            $ind = 'trans' === $node->getNode('filter')->getAttribute('value') ? 1 : 2;
            $arguments = $node->getNode('arguments');
            if (!$arguments->hasNode($ind)) {
                if (!$arguments->hasNode($ind - 1)) {
                    $arguments->setNode($ind - 1, new \Twig_Node_Expression_Array(array(), $node->getLine()));
                }

                $arguments->setNode($ind, $this->domain);
            }
        } elseif ($node instanceof TransNode) {
            if (null === $node->getNode('domain')) {
                $node->setNode('domain', $this->domain);
            }
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    public function leaveNode(\Twig_NodeInterface $node, \Twig_Environment $env)
    {
        if ($node instanceof TransDefaultDomainNode) {
            return false;
        }

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
