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
class TranslationDefaultDomainNodeVisitor extends \Twig_BaseNodeVisitor
{
    /**
     * @var Scope
     */
    private $scope;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->scope = new Scope();
    }

    /**
     * {@inheritdoc}
     */
    protected function doEnterNode(\Twig_Node $node, \Twig_Environment $env)
    {
        if ($node instanceof \Twig_Node_Block || $node instanceof \Twig_Node_Module) {
            $this->scope = $this->scope->enter();
        }

        if ($node instanceof TransDefaultDomainNode) {
            if ($node->getNode('expr') instanceof \Twig_Node_Expression_Constant) {
                $this->scope->set('domain', $node->getNode('expr'));

                return $node;
            } else {
                $var = $this->getVarName();
                $name = new \Twig_Node_Expression_AssignName($var, $node->getTemplateLine());
                $this->scope->set('domain', new \Twig_Node_Expression_Name($var, $node->getTemplateLine()));

                return new \Twig_Node_Set(false, new \Twig_Node(array($name)), new \Twig_Node(array($node->getNode('expr'))), $node->getTemplateLine());
            }
        }

        if (!$this->scope->has('domain')) {
            return $node;
        }

        if ($node instanceof \Twig_Node_Expression_Filter && in_array($node->getNode('filter')->getAttribute('value'), array('trans', 'transchoice'))) {
            $arguments = $node->getNode('arguments');
            $ind = 'trans' === $node->getNode('filter')->getAttribute('value') ? 1 : 2;
            if ($this->isNamedArguments($arguments)) {
                if (!$arguments->hasNode('domain') && !$arguments->hasNode($ind)) {
                    $arguments->setNode('domain', $this->scope->get('domain'));
                }
            } else {
                if (!$arguments->hasNode($ind)) {
                    if (!$arguments->hasNode($ind - 1)) {
                        $arguments->setNode($ind - 1, new \Twig_Node_Expression_Array(array(), $node->getTemplateLine()));
                    }

                    $arguments->setNode($ind, $this->scope->get('domain'));
                }
            }
        } elseif ($node instanceof TransNode) {
            if (!$node->hasNode('domain')) {
                $node->setNode('domain', $this->scope->get('domain'));
            }
        }

        return $node;
    }

    /**
     * {@inheritdoc}
     */
    protected function doLeaveNode(\Twig_Node $node, \Twig_Environment $env)
    {
        if ($node instanceof TransDefaultDomainNode) {
            return false;
        }

        if ($node instanceof \Twig_Node_Block || $node instanceof \Twig_Node_Module) {
            $this->scope = $this->scope->leave();
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

    /**
     * @return bool
     */
    private function isNamedArguments($arguments)
    {
        foreach ($arguments as $name => $node) {
            if (!is_int($name)) {
                return true;
            }
        }

        return false;
    }

    private function getVarName()
    {
        return sprintf('__internal_%s', hash('sha256', uniqid(mt_rand(), true), false));
    }
}
