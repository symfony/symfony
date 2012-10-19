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
use Symfony\Bridge\Twig\Node\TransDefaultVarsNode;

/**
 * TranslationDefaultDomainNodeVisitor.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexandre Salomé <alexandre.salome@gmail.com>
 */
class TranslationDefaultsNodeVisitor implements \Twig_NodeVisitorInterface
{
    private $domain;
    private $vars;

    /**
     * {@inheritdoc}
     */
    public function enterNode(\Twig_NodeInterface $node, \Twig_Environment $env)
    {
        if ($node instanceof \Twig_Node_Module) {
            $this->domain = null;
        }

        if ($node instanceof TransDefaultDomainNode || $node instanceof TransDefaultVarsNode) {
            $var = $env->getParser()->getVarName();
            $name = new \Twig_Node_Expression_AssignName($var, $node->getLine());
            if ($node instanceof TransDefaultDomainNode) {
                $this->domain = new \Twig_Node_Expression_Name($var, $node->getLine());

                return new \Twig_Node_Set(false, new \Twig_Node(array($name)), new \Twig_Node(array($node->getNode('expr'))), $node->getLine());
            } elseif ($node instanceof TransDefaultVarsNode) {
                $this->vars = new \Twig_Node_Expression_Name($var, $node->getLine());

                return new \Twig_Node_Set(false, new \Twig_Node(array($name)), new \Twig_Node(array($node->getNode('expr'))), $node->getLine());
            }
        }

        if (null === $this->domain && null === $this->vars) {
            return $node;
        }

        if ($node instanceof \Twig_Node_Expression_Filter && in_array($node->getNode('filter')->getAttribute('value'), array('trans', 'transchoice'))) {
            $this->addDefaultsToFilter($node);
        } elseif ($node instanceof TransNode) {
            $this->addDefaultsToTransNode($node);
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

    private function addDefaultsToFilter(\Twig_Node_Expression_Filter $node)
    {
        $ind = 'trans' === $node->getNode('filter')->getAttribute('value') ? 0 : 1;
        $arguments = $node->getNode('arguments');

        if (null !== $this->vars && !$arguments->hasNode($ind)) {
            $arguments->setNode($ind, $this->vars);
        } elseif (!$arguments->hasNode($ind)) {
            $arguments->setNode($ind, new \Twig_Node_Expression_Array(array(), $node->getLine()));
        }

        if (null !== $this->domain && !$arguments->hasNode($ind + 1)) {
            $arguments->setNode($ind + 1, $this->domain);
        }
    }

    private function addDefaultsToTransNode(TransNode $node)
    {
        if (null === $node->getNode('domain')) {
                $node->setNode('domain', $this->domain);
        }
        if (null === $node->getNode('vars')) {
            $node->setNode('vars', $this->vars);
        }
    }
}
